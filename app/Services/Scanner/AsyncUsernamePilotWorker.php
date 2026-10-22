<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use App\Jobs\RunValidatorJob;
use Closure;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Throwable;

/** Opt-in local/staging pilot only. This does not replace queue:work. */
final class AsyncUsernamePilotWorker
{
    public function work(int $concurrency, int $maxJobs = 300, int $maxTime = 3600, bool $stopWhenEmpty = true, int $memory = 256): array
    {
        $scheduler = app(ScanJobScheduler::class);
        if (app()->isProduction() || ! config('scanner.async.username_pilot.enabled', false) || ! $scheduler->usesFairScheduling()) {
            throw new RuntimeException('Async username pilot is disabled, production is not permitted, or fair database scheduling is unavailable.');
        }
        $limit = (int) config('scanner.async.username_pilot.concurrency', 40);
        if ($concurrency < 1 || $concurrency > min(60, $limit) || $maxJobs < 0 || $maxTime < 1 || $memory < 1) {
            throw new RuntimeException('Invalid pilot worker limits.');
        }
        if (in_array(config('cache.default'), ['array', 'null'], true) && ! app()->environment('testing')) {
            throw new RuntimeException('A shared cache store is required for the global async pilot budget.');
        }

        $timeout = max(1, (int) config('scanner.async.job_timeout', 60));
        // Reserve capacity, not one lock per request. Several small processes
        // may share CPU cores without multiplying the global pilot budget.
        $locks = [];
        for ($slot = 0; $slot < min(60, $limit) && count($locks) < $concurrency; $slot++) {
            $lock = Cache::lock('scanner:async-username-pilot:slot:'.$slot, $maxTime + $timeout + 180);
            if ($lock->get()) {
                $locks[] = $lock;
            }
        }
        if (count($locks) !== $concurrency) {
            foreach ($locks as $lock) {
                $lock->release();
            }
            throw new RuntimeException('Insufficient unreserved slots in the global async pilot budget.');
        }

        $started = microtime(true);
        $lastRestart = Cache::get('illuminate:queue:restart');
        $accepted = 0;
        $completed = 0;
        $peak = 0;
        $stopping = false;
        $signals = [];
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            foreach ([SIGTERM, SIGINT, SIGQUIT] as $signal) {
                $signals[$signal] = pcntl_signal_get_handler($signal);
                pcntl_signal($signal, function () use (&$stopping): void {
                    $stopping = true;
                });
            }
        }

        try {
            do {
                $stats = (new AsyncPilotRuntime)->run(function () use ($scheduler, &$accepted, $started, $maxJobs, $maxTime, $memory, &$stopping, $lastRestart): ?Closure {
                    if ($stopping || ($maxJobs > 0 && $accepted >= $maxJobs) || microtime(true) - $started >= $maxTime
                        || memory_get_usage(true) >= $memory * 1024 * 1024) {
                        return null;
                    }
                    if (Cache::get('illuminate:queue:restart') !== $lastRestart) {
                        $stopping = true;

                        return null;
                    }
                    $job = Queue::connection($scheduler->connection())->pop((string) config('scanner.async.username_pilot.queue'));
                    if ($job === null) {
                        return null;
                    }
                    $payload = $job->payload();
                    $command = ($payload['data']['commandName'] ?? null) === RunValidatorJob::class
                        ? unserialize($payload['data']['command']) : null;
                    if (! $command instanceof RunValidatorJob
                        || ! app(ScannerEngineService::class)->supportsAsyncPilot($command->mode, $command->validatorKey)) {
                        $job->release();
                        throw new RuntimeException('Non-pilot job found on the dedicated pilot queue; job released without executing.');
                    }
                    $accepted++;

                    return function () use ($job, $command): void {
                        try {
                            app('events')->dispatch(new JobProcessing($job->getConnectionName(), $job));
                            if ($command->tries > 0 && $job->attempts() > $command->tries) {
                                throw MaxAttemptsExceededException::forJob($job);
                            }
                            // Uses the original Laravel job handler, scheduler,
                            // skip policy, normalizers, metadata and result store.
                            $job->fire();
                            app('events')->dispatch(new JobProcessed($job->getConnectionName(), $job));
                        } catch (Throwable $error) {
                            app('events')->dispatch(new JobExceptionOccurred($job->getConnectionName(), $job, $error));
                            $job->fail($error);
                        }
                    };
                }, $concurrency, $timeout);
                $completed += $stats['completed'];
                $peak = max($peak, $stats['peak_tasks']);
                if ($stopWhenEmpty || $stopping || ($maxJobs > 0 && $accepted >= $maxJobs)
                    || memory_get_usage(true) >= $memory * 1024 * 1024) {
                    break;
                }
                usleep(1_000_000);
            } while (microtime(true) - $started < $maxTime);
        } finally {
            foreach ($signals as $signal => $handler) {
                pcntl_signal($signal, $handler);
            }
            foreach ($locks as $lock) {
                $lock->release();
            }
        }

        return ['completed' => $completed, 'peak_tasks' => $peak, 'elapsed_seconds' => microtime(true) - $started];
    }
}
