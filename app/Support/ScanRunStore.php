<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class ScanRunStore
{
    /**
     * @param array<int, string> $targets
     * @param array<string, mixed> $options
     * @param array<int, string> $expandedTargets
     * @param array<int, string> $selectedValidatorKeys
     */
    public function createRun(
        string $mode,
        array $targets,
        array $options = [],
        array $expandedTargets = [],
        int $validatorCount = 0,
        int $expectedResults = 0,
        array $selectedValidatorKeys = [],
    ): string {
        $id = bin2hex(random_bytes(8));
        $now = now();

        DB::table('scan_runs')->insert([
            'id' => $id,
            'mode' => $mode,
            'status' => $expectedResults === 0 ? 'completed' : 'queued',
            'target_count' => count($targets),
            'validator_count' => $validatorCount,
            'expected_results' => $expectedResults,
            'total' => $expectedResults,
            'processed' => 0,
            'queued_jobs' => $expectedResults,
            'running_jobs' => 0,
            'completed_jobs' => 0,
            'targets' => json_encode(array_values($targets), JSON_THROW_ON_ERROR),
            'selected_validator_keys' => json_encode(array_values($selectedValidatorKeys), JSON_THROW_ON_ERROR),
            'options' => json_encode($options, JSON_THROW_ON_ERROR),
            'expanded_targets' => json_encode(array_values($expandedTargets), JSON_THROW_ON_ERROR),
            'completed_at' => $expectedResults === 0 ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    public function markJobStarted(string $runId): void
    {
        DB::transaction(function () use ($runId): void {
            $run = DB::table('scan_runs')->where('id', $runId)->lockForUpdate()->first();
            if (!$run) {
                return;
            }

            DB::table('scan_runs')
                ->where('id', $runId)
                ->update([
                    'status' => 'running',
                    'queued_jobs' => max(0, (int) $run->queued_jobs - 1),
                    'running_jobs' => (int) $run->running_jobs + 1,
                    'updated_at' => now(),
                ]);
        });
    }

    /**
     * @param array<string,mixed> $result
     */
    public function appendResult(string $runId, array $result, int $targetIndex = 0, int $validatorIndex = 0): void
    {
        DB::transaction(function () use ($runId, $result, $targetIndex, $validatorIndex): void {
            $run = DB::table('scan_runs')->where('id', $runId)->lockForUpdate()->first();
            if (!$run) {
                return;
            }

            DB::table('scan_run_results')->insert([
                'scan_run_id' => $runId,
                'target' => (string) ($result['target'] ?? ''),
                'category' => strtolower((string) ($result['category'] ?? '')),
                'site_name' => (string) ($result['site_name'] ?? ''),
                'url' => (string) ($result['url'] ?? ''),
                'status' => (string) ($result['status'] ?? ''),
                'reason' => (string) ($result['reason'] ?? ''),
                'extra' => (string) ($result['extra'] ?? ''),
                'mode' => (string) ($result['mode'] ?? ''),
                'key' => (string) ($result['key'] ?? ''),
                'target_index' => $targetIndex,
                'validator_index' => $validatorIndex,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $processed = (int) $run->processed + 1;
            $completed = (int) $run->completed_jobs + 1;
            $running = max(0, (int) $run->running_jobs - 1);
            $expected = (int) $run->expected_results;
            $status = $processed >= $expected ? 'completed' : 'running';

            DB::table('scan_runs')->where('id', $runId)->update([
                'processed' => $processed,
                'completed_jobs' => $completed,
                'running_jobs' => $running,
                'status' => $status,
                'completed_at' => $status === 'completed' ? now() : null,
                'updated_at' => now(),
            ]);
        });
    }

    public function failRun(string $runId, string $message): void
    {
        DB::table('scan_runs')
            ->where('id', $runId)
            ->update([
                'status' => 'failed',
                'error' => $message,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param array<string, mixed> $result
     */
    public function appendFailedResult(string $runId, array $result, int $targetIndex = 0, int $validatorIndex = 0): void
    {
        DB::transaction(function () use ($runId, $result, $targetIndex, $validatorIndex): void {
            $run = DB::table('scan_runs')->where('id', $runId)->lockForUpdate()->first();
            if (!$run) {
                return;
            }

            $existing = DB::table('scan_run_results')
                ->where('scan_run_id', $runId)
                ->where('target_index', $targetIndex)
                ->where('validator_index', $validatorIndex)
                ->exists();

            if (!$existing) {
                DB::table('scan_run_results')->insert([
                    'scan_run_id' => $runId,
                    'target' => (string) ($result['target'] ?? ''),
                    'category' => strtolower((string) ($result['category'] ?? '')),
                    'site_name' => (string) ($result['site_name'] ?? ''),
                    'url' => (string) ($result['url'] ?? ''),
                    'status' => (string) ($result['status'] ?? ''),
                    'reason' => (string) ($result['reason'] ?? ''),
                    'extra' => (string) ($result['extra'] ?? ''),
                    'mode' => (string) ($result['mode'] ?? ''),
                    'key' => (string) ($result['key'] ?? ''),
                    'target_index' => $targetIndex,
                    'validator_index' => $validatorIndex,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $processed = (int) $run->processed + ($existing ? 0 : 1);
            $queued = (int) $run->queued_jobs;
            $running = (int) $run->running_jobs;

            if ($running > 0) {
                $running--;
            } elseif ($queued > 0) {
                $queued--;
            }

            $completed = min((int) $run->expected_results, (int) $run->completed_jobs + ($existing ? 0 : 1));
            $expected = (int) $run->expected_results;
            $status = $processed >= $expected ? 'completed' : 'running';

            DB::table('scan_runs')->where('id', $runId)->update([
                'processed' => $processed,
                'queued_jobs' => $queued,
                'running_jobs' => $running,
                'completed_jobs' => $completed,
                'status' => $status,
                'completed_at' => $status === 'completed' ? now() : null,
                'updated_at' => now(),
            ]);
        });
    }

    /** @return array<string,mixed>|null */
    public function getRun(string $runId): ?array
    {
        $run = DB::table('scan_runs')->where('id', $runId)->first();

        return $run ? $this->hydrateRun($run) : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function listRuns(?string $status = null): array
    {
        $query = DB::table('scan_runs')->orderByDesc('created_at');
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        return array_map(fn ($run): array => $this->hydrateRun($run), $query->get()->all());
    }

    /** @return array<int,array<string,mixed>> */
    public function filteredResults(string $runId, ?string $status = null, ?string $category = null, ?bool $onlyHits = null): array
    {
        $run = $this->getRun($runId);
        if (!$run) {
            return [];
        }

        $query = DB::table('scan_run_results')
            ->where('scan_run_id', $runId)
            ->orderBy('target_index')
            ->orderBy('validator_index')
            ->orderBy('id');

        if ($status) {
            $query->where('status', $status);
        } elseif ($onlyHits ?? (($run['options']['only_found'] ?? false) === true)) {
            $query->whereIn('status', ['Found', 'Registered']);
        }

        if ($category) {
            $query->whereRaw('lower(category) = ?', [strtolower($category)]);
        }

        return array_map(static function ($row): array {
            return [
                'target' => (string) $row->target,
                'category' => strtolower((string) ($row->category ?? '')),
                'site_name' => (string) ($row->site_name ?? ''),
                'url' => (string) ($row->url ?? ''),
                'status' => (string) ($row->status ?? ''),
                'reason' => (string) ($row->reason ?? ''),
                'extra' => (string) ($row->extra ?? ''),
                'mode' => (string) ($row->mode ?? ''),
                'key' => (string) ($row->key ?? ''),
            ];
        }, $query->get()->all());
    }

    /**
     * @param object $run
     * @return array<string,mixed>
     */
    private function hydrateRun(object $run): array
    {
        return [
            'id' => (string) $run->id,
            'mode' => (string) $run->mode,
            'status' => (string) $run->status,
            'target_count' => (int) $run->target_count,
            'validator_count' => (int) $run->validator_count,
            'expected_results' => (int) $run->expected_results,
            'total' => (int) $run->total,
            'processed' => (int) $run->processed,
            'queued_jobs' => (int) $run->queued_jobs,
            'running_jobs' => (int) $run->running_jobs,
            'completed_jobs' => (int) $run->completed_jobs,
            'targets' => $this->decodeJsonArray($run->targets ?? null),
            'selected_validator_keys' => $this->decodeJsonArray($run->selected_validator_keys ?? null),
            'options' => $this->decodeJsonAssoc($run->options ?? null),
            'expanded_targets' => $this->decodeJsonArray($run->expanded_targets ?? null),
            'created_at' => $run->created_at,
            'updated_at' => $run->updated_at,
            'completed_at' => $run->completed_at,
            'error' => $run->error,
        ];
    }

    /** @return array<int,mixed> */
    private function decodeJsonArray(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /** @return array<string,mixed> */
    private function decodeJsonAssoc(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
