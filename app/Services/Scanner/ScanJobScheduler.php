<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use App\Jobs\RunValidatorJob;
use App\Support\ScanRunStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

/**
 * Keep only a bounded window from each run on its FIFO queue. Replacements go
 * to the tail, behind other runs, rather than enqueuing a whole large scan.
 * Task transitions, results and queue inserts share one database transaction.
 */
final class ScanJobScheduler
{
    public function connection(): string
    {
        return (string) (config('scanner.async.connection') ?: config('queue.default'));
    }

    public function queueFor(string $mode): string
    {
        return (string) config('scanner.async.queues.'.$mode, config('scanner.async.queue', 'scanner'));
    }

    /** @return array<int, string> */
    public function queues(): array
    {
        return array_values(array_unique([
            (string) config('scanner.async.queue', 'scanner'),
            $this->queueFor('email'),
            $this->queueFor('username'),
        ]));
    }

    public function usesFairScheduling(): bool
    {
        if (! config('scanner.async.fair_scheduling', true)) {
            return false;
        }

        $connection = config('queue.connections.'.$this->connection(), []);
        if (($connection['driver'] ?? null) === 'sync') {
            return false;
        }

        if (($connection['driver'] ?? null) !== 'database'
            || ($connection['connection'] ?? config('database.default')) !== config('database.default')) {
            throw new RuntimeException('Fair scanner scheduling requires a database queue on the same database connection as scan_runs.');
        }

        return true;
    }

    /** @param array<int, RunValidatorJob> $jobs */
    public function stage(string $runId, array $jobs): void
    {
        DB::transaction(function () use ($runId, $jobs): void {
            $run = DB::table('scan_runs')->where('id', $runId)->lockForUpdate()->first();
            if ($run === null) {
                throw new RuntimeException('Cannot schedule a missing scan run.');
            }

            $rows = [];
            foreach ($jobs as $job) {
                $rows[] = [
                    'scan_run_id' => $runId,
                    'queue' => $this->queueFor($job->mode),
                    // Shared options (including proxies) are stored once in scan_runs.
                    'payload' => json_encode([
                        'validator_key' => $job->validatorKey,
                        'validator_meta' => $job->validatorMeta,
                        'target' => $job->target,
                        'target_index' => $job->targetIndex,
                        'validator_index' => $job->validatorIndex,
                        'proxy_offset' => $job->proxyOffset,
                    ], JSON_THROW_ON_ERROR),
                    'available_at' => $job->delay instanceof \DateTimeInterface
                        ? $job->delay->getTimestamp() : now()->getTimestamp(),
                ];
            }

            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('scan_run_tasks')->insert($chunk);
            }

            $this->fillWindow($run);
        }, 3);
    }

    public function start(RunValidatorJob $job, ScanRunStore $store): bool
    {
        return DB::transaction(function () use ($job, $store): bool {
            $run = DB::table('scan_runs')->where('id', $job->runId)->lockForUpdate()->first();
            $task = $this->task($job);
            if ($run === null || $task === null || ! in_array($run->status, ['queued', 'running'], true)) {
                return false;
            }

            if ($task->started_at === null) {
                $store->markJobStarted($job->runId);
                DB::table('scan_run_tasks')->where('id', $task->id)->update(['started_at' => now()]);
            }

            return true;
        }, 3);
    }

    /** @param array<string, mixed> $result */
    public function finish(RunValidatorJob $job, ScanRunStore $store, array $result): void
    {
        DB::transaction(function () use ($job, $store, $result): void {
            $run = DB::table('scan_runs')->where('id', $job->runId)->lockForUpdate()->first();
            $task = $this->task($job);
            if ($run === null || $task === null || ! in_array($run->status, ['queued', 'running'], true)) {
                return;
            }

            if ($task->started_at === null) {
                // A worker can fail (e.g. tries exhausted) before handle() starts.
                // Start this particular slot before settling it, rather than
                // decrementing a different task's running counter.
                $store->markJobStarted($job->runId);
            }
            $store->appendResult($job->runId, $result, $job->targetIndex, $job->validatorIndex);

            DB::table('scan_run_tasks')->where('id', $task->id)->delete();
            $this->fillWindow($run);
        }, 3);
    }

    private function task(RunValidatorJob $job): ?object
    {
        return DB::table('scan_run_tasks')
            ->where('scan_run_id', $job->runId)
            ->where('id', $job->scheduledTaskId)
            ->whereNotNull('dispatched_at')
            ->first();
    }

    /** Called only while holding the parent scan_runs row lock. */
    private function fillWindow(object $run): void
    {
        $window = max(1, (int) config('scanner.async.windows.'.$run->mode, 1));
        $inFlight = DB::table('scan_run_tasks')->where('scan_run_id', $run->id)->whereNotNull('dispatched_at')->count();
        if ($inFlight >= $window) {
            return;
        }

        $tasks = DB::table('scan_run_tasks')
            ->where('scan_run_id', $run->id)
            ->whereNull('dispatched_at')
            ->orderBy('id')
            ->limit($window - $inFlight)
            ->get();
        $options = json_decode((string) $run->options, true, 512, JSON_THROW_ON_ERROR);

        foreach ($tasks as $task) {
            $payload = json_decode((string) $task->payload, true, 512, JSON_THROW_ON_ERROR);
            $job = new RunValidatorJob(
                runId: (string) $run->id,
                mode: (string) $run->mode,
                validatorKey: $payload['validator_key'],
                validatorMeta: $payload['validator_meta'],
                target: $payload['target'],
                targetIndex: $payload['target_index'],
                validatorIndex: $payload['validator_index'],
                options: $options,
                proxyOffset: $payload['proxy_offset'],
            );
            $job->scheduledTaskId = (int) $task->id;
            // Force an immediate insert into the enclosing DB transaction, even
            // if the queue connection is configured with after_commit=true.
            $job->onConnection($this->connection())->onQueue($task->queue)->beforeCommit();
            DB::table('scan_run_tasks')->where('id', $task->id)->update(['dispatched_at' => now()]);

            $queue = Queue::connection($this->connection());
            $delay = max(0, (int) $task->available_at - now()->getTimestamp());
            if ($delay > 0) {
                $queue->later($delay, $job, '', $task->queue);
            } else {
                $queue->push($job, '', $task->queue);
            }
        }
    }
}
