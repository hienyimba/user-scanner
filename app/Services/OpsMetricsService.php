<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class OpsMetricsService
{
    public const WINDOW_PRESETS = [
        '30d' => ['label' => '30-day window', 'hours' => 24 * 30, 'bucket' => 'day'],
        '7d' => ['label' => '7-day window', 'hours' => 24 * 7, 'bucket' => 'day'],
        '1d' => ['label' => '1-day window', 'hours' => 24, 'bucket' => 'hour'],
        '6h' => ['label' => '6hrs', 'hours' => 6, 'bucket' => 'hour'],
    ];

    public function recordPublicScanRequest(
        string $mode,
        string $target,
        ?string $category,
        ?string $runId,
        bool $ok,
        bool $reused,
        bool $cached,
        ?string $error = null,
    ): void {
        DB::table('public_scan_request_events')->insert([
            'run_id' => $runId,
            'mode' => $mode,
            'category' => $category,
            'target_hash' => hash('sha256', strtolower(trim($target))),
            'ok' => $ok,
            'reused' => $reused,
            'cached' => $cached,
            'error' => $error,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function captureQueueSnapshot(): array
    {
        $queue = (string) config('scanner.async.queue', 'scanner');

        $queuedJobs = DB::table('jobs')
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->count();

        $reservedJobs = DB::table('jobs')
            ->where('queue', $queue)
            ->whereNotNull('reserved_at')
            ->count();

        $activeRuns = DB::table('scan_runs')
            ->whereIn('status', ['queued', 'running'])
            ->get(['status', 'expected_results', 'processed']);

        $queuedRuns = 0;
        $runningRuns = 0;
        $outstandingResults = 0;

        foreach ($activeRuns as $run) {
            if (($run->status ?? null) === 'queued') {
                $queuedRuns++;
            }

            if (($run->status ?? null) === 'running') {
                $runningRuns++;
            }

            $outstandingResults += max(0, (int) ($run->expected_results ?? 0) - (int) ($run->processed ?? 0));
        }

        $snapshot = [
            'queue_name' => $queue,
            'queued_jobs' => $queuedJobs,
            'reserved_jobs' => $reservedJobs,
            'active_runs' => $queuedRuns + $runningRuns,
            'queued_runs' => $queuedRuns,
            'running_runs' => $runningRuns,
            'outstanding_results' => $outstandingResults,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('ops_queue_snapshots')->insert($snapshot);

        return [
            'queued_jobs' => $queuedJobs,
            'reserved_jobs' => $reservedJobs,
            'active_runs' => $queuedRuns + $runningRuns,
            'queued_runs' => $queuedRuns,
            'running_runs' => $runningRuns,
            'outstanding_results' => $outstandingResults,
            'captured_at' => $snapshot['created_at']->toIso8601String(),
        ];
    }

    public function ensureRecentQueueSnapshot(int $maxAgeMinutes = 15): void
    {
        $latest = DB::table('ops_queue_snapshots')
            ->orderByDesc('created_at')
            ->value('created_at');

        if ($latest === null) {
            $this->captureQueueSnapshot();

            return;
        }

        $latestTime = Carbon::parse((string) $latest);
        if ($latestTime->lt(now()->subMinutes($maxAgeMinutes))) {
            $this->captureQueueSnapshot();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(string $window = '30d'): array
    {
        $windowConfig = $this->resolveWindow($window);
        $range = $this->buildBucketRange($windowConfig['bucket'], $windowConfig['hours']);

        $completion = $this->completionMetrics($range);
        $p95 = $this->p95Metrics($range);
        $reuse = $this->reuseMetrics($range);
        $validatorErrors = $this->validatorErrorMetrics($range['start']);
        $storage = $this->storageMetrics($range);
        $queue = $this->queueBacklogMetrics($range);

        return [
            'window' => $windowConfig['key'],
            'window_label' => $windowConfig['label'],
            'window_hours' => $windowConfig['hours'],
            'bucket' => $windowConfig['bucket'],
            'completion' => $completion,
            'p95' => $p95,
            'reuse' => $reuse,
            'validator_errors' => $validatorErrors,
            'storage' => $storage,
            'queue' => $queue,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function completionMetrics(array $range): array
    {
        $series = [];
        foreach ($range['keys'] as $key) {
            $series[$key] = ['completed' => 0, 'failed' => 0];
        }

        $completed = 0;
        $failed = 0;

        $rows = DB::table('scan_runs')
            ->whereIn('status', ['completed', 'failed'])
            ->where('updated_at', '>=', $range['start'])
            ->get(['status', 'completed_at', 'updated_at']);

        foreach ($rows as $row) {
            $settledAt = $this->settledAt((string) $row->status, $row->completed_at, $row->updated_at);
            if ($settledAt === null || $settledAt->lt($range['start'])) {
                continue;
            }

            $bucketKey = $this->bucketKey($settledAt, $range['bucket']);
            if (!isset($series[$bucketKey])) {
                continue;
            }

            if ((string) $row->status === 'completed') {
                $completed++;
                $series[$bucketKey]['completed']++;
                continue;
            }

            $failed++;
            $series[$bucketKey]['failed']++;
        }

        $settled = $completed + $failed;

        return [
            'completed' => $completed,
            'failed' => $failed,
            'settled' => $settled,
            'rate' => $settled > 0 ? round(($completed / $settled) * 100, 1) : null,
            'chart' => [
                'labels' => $range['labels'],
                'completed' => array_map(static fn (string $key): int => $series[$key]['completed'], $range['keys']),
                'failed' => array_map(static fn (string $key): int => $series[$key]['failed'], $range['keys']),
                'rate' => array_map(
                    static function (string $key) use ($series): ?float {
                        $total = $series[$key]['completed'] + $series[$key]['failed'];

                        return $total > 0 ? round(($series[$key]['completed'] / $total) * 100, 1) : null;
                    },
                    $range['keys'],
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function p95Metrics(array $range): array
    {
        $bucketDurations = [];
        foreach ($range['keys'] as $key) {
            $bucketDurations[$key] = [];
        }

        $allDurations = [];

        $rows = DB::table('scan_runs')
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $range['start'])
            ->get(['created_at', 'completed_at']);

        foreach ($rows as $row) {
            $createdAt = $this->parseDate($row->created_at);
            $completedAt = $this->parseDate($row->completed_at);
            if ($createdAt === null || $completedAt === null) {
                continue;
            }

            $durationSeconds = max(0, $createdAt->diffInSeconds($completedAt));
            $bucketKey = $this->bucketKey($completedAt, $range['bucket']);
            if (!isset($bucketDurations[$bucketKey])) {
                continue;
            }

            $allDurations[] = $durationSeconds;
            $bucketDurations[$bucketKey][] = $durationSeconds;
        }

        $overall = $this->percentile($allDurations, 95);

        return [
            'seconds' => $overall,
            'display' => $this->formatDuration($overall),
            'samples' => count($allDurations),
            'chart' => [
                'labels' => $range['labels'],
                'seconds' => array_map(
                    fn (string $key): ?int => $this->percentile($bucketDurations[$key], 95),
                    $range['keys'],
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reuseMetrics(array $range): array
    {
        $series = [];
        foreach ($range['keys'] as $key) {
            $series[$key] = ['total' => 0, 'reused' => 0, 'cached' => 0];
        }

        $total = 0;
        $reused = 0;
        $cached = 0;

        $rows = DB::table('public_scan_request_events')
            ->where('created_at', '>=', $range['start'])
            ->get(['created_at', 'ok', 'reused', 'cached']);

        foreach ($rows as $row) {
            if (!(bool) $row->ok) {
                continue;
            }

            $createdAt = $this->parseDate($row->created_at);
            if ($createdAt === null) {
                continue;
            }

            $bucketKey = $this->bucketKey($createdAt, $range['bucket']);
            if (!isset($series[$bucketKey])) {
                continue;
            }

            $total++;
            $series[$bucketKey]['total']++;

            if ((bool) $row->reused) {
                $reused++;
                $series[$bucketKey]['reused']++;
            }

            if ((bool) $row->cached) {
                $cached++;
                $series[$bucketKey]['cached']++;
            }
        }

        return [
            'total' => $total,
            'reused' => $reused,
            'cached' => $cached,
            'rate' => $total > 0 ? round(($reused / $total) * 100, 1) : null,
            'cached_rate' => $total > 0 ? round(($cached / $total) * 100, 1) : null,
            'chart' => [
                'labels' => $range['labels'],
                'total' => array_map(static fn (string $key): int => $series[$key]['total'], $range['keys']),
                'reused' => array_map(static fn (string $key): int => $series[$key]['reused'], $range['keys']),
                'cached' => array_map(static fn (string $key): int => $series[$key]['cached'], $range['keys']),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatorErrorMetrics(CarbonInterface $startDay): array
    {
        $rows = DB::table('scan_run_results')
            ->where('created_at', '>=', $startDay)
            ->get(['key', 'site_name', 'status']);

        $modules = [];
        $totalResults = 0;
        $totalErrors = 0;

        foreach ($rows as $row) {
            $key = trim((string) ($row->key ?? ''));
            if ($key === '') {
                continue;
            }

            if (!isset($modules[$key])) {
                $modules[$key] = [
                    'key' => $key,
                    'label' => trim((string) ($row->site_name ?? '')) ?: $key,
                    'total' => 0,
                    'errors' => 0,
                    'rate' => 0.0,
                ];
            }

            $modules[$key]['total']++;
            $totalResults++;

            if ((string) ($row->status ?? '') === 'Error') {
                $modules[$key]['errors']++;
                $totalErrors++;
            }
        }

        foreach ($modules as $key => $module) {
            $modules[$key]['rate'] = $module['total'] > 0
                ? round(($module['errors'] / $module['total']) * 100, 1)
                : 0.0;
        }

        usort($modules, static function (array $left, array $right): int {
            return [$right['rate'], $right['errors'], $right['total']]
                <=> [$left['rate'], $left['errors'], $left['total']];
        });

        $topModules = array_slice($modules, 0, 12);

        return [
            'overall_rate' => $totalResults > 0 ? round(($totalErrors / $totalResults) * 100, 1) : null,
            'total_results' => $totalResults,
            'total_errors' => $totalErrors,
            'modules' => array_slice($modules, 0, 15),
            'chart' => [
                'labels' => array_map(static fn (array $module): string => $module['label'], $topModules),
                'rates' => array_map(static fn (array $module): float => $module['rate'], $topModules),
                'errors' => array_map(static fn (array $module): int => $module['errors'], $topModules),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function storageMetrics(array $range): array
    {
        $runBuckets = array_fill_keys($range['keys'], 0);
        $resultBuckets = array_fill_keys($range['keys'], 0);

        $runRows = DB::table('scan_runs')
            ->where('created_at', '>=', $range['start'])
            ->get(['created_at']);

        foreach ($runRows as $row) {
            $createdAt = $this->parseDate($row->created_at);
            if ($createdAt === null) {
                continue;
            }

            $bucketKey = $this->bucketKey($createdAt, $range['bucket']);
            if (isset($runBuckets[$bucketKey])) {
                $runBuckets[$bucketKey]++;
            }
        }

        $resultRows = DB::table('scan_run_results')
            ->where('created_at', '>=', $range['start'])
            ->get(['created_at']);

        foreach ($resultRows as $row) {
            $createdAt = $this->parseDate($row->created_at);
            if ($createdAt === null) {
                continue;
            }

            $bucketKey = $this->bucketKey($createdAt, $range['bucket']);
            if (isset($resultBuckets[$bucketKey])) {
                $resultBuckets[$bucketKey]++;
            }
        }

        $runBaseline = DB::table('scan_runs')->where('created_at', '<', $range['start'])->count();
        $resultBaseline = DB::table('scan_run_results')->where('created_at', '<', $range['start'])->count();

        $runCumulative = [];
        $resultCumulative = [];
        $runTotal = $runBaseline;
        $resultTotal = $resultBaseline;

        foreach ($range['keys'] as $key) {
            $runTotal += $runBuckets[$key];
            $resultTotal += $resultBuckets[$key];
            $runCumulative[] = $runTotal;
            $resultCumulative[] = $resultTotal;
        }

        return [
            'total_runs' => DB::table('scan_runs')->count(),
            'total_results' => DB::table('scan_run_results')->count(),
            'window_runs' => array_sum($runBuckets),
            'window_results' => array_sum($resultBuckets),
            'chart' => [
                'labels' => $range['labels'],
                'runs_daily' => array_map(static fn (string $key): int => $runBuckets[$key], $range['keys']),
                'results_daily' => array_map(static fn (string $key): int => $resultBuckets[$key], $range['keys']),
                'runs_cumulative' => $runCumulative,
                'results_cumulative' => $resultCumulative,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queueBacklogMetrics(array $range): array
    {
        $bucketRows = [];
        foreach ($range['keys'] as $key) {
            $bucketRows[$key] = null;
        }

        $rows = DB::table('ops_queue_snapshots')
            ->where('created_at', '>=', $range['start'])
            ->orderBy('created_at')
            ->get([
                'created_at',
                'queued_jobs',
                'reserved_jobs',
                'active_runs',
                'queued_runs',
                'running_runs',
                'outstanding_results',
            ]);

        $latest = null;

        foreach ($rows as $row) {
            $capturedAt = $this->parseDate($row->created_at);
            if ($capturedAt === null) {
                continue;
            }

            $bucketKey = $this->bucketKey($capturedAt, $range['bucket']);
            if (isset($bucketRows[$bucketKey])) {
                $bucketRows[$bucketKey] = $row;
            }

            $latest = [
                'queued_jobs' => (int) ($row->queued_jobs ?? 0),
                'reserved_jobs' => (int) ($row->reserved_jobs ?? 0),
                'active_runs' => (int) ($row->active_runs ?? 0),
                'queued_runs' => (int) ($row->queued_runs ?? 0),
                'running_runs' => (int) ($row->running_runs ?? 0),
                'outstanding_results' => (int) ($row->outstanding_results ?? 0),
                'captured_at' => $capturedAt->toIso8601String(),
                'captured_label' => $capturedAt->format('M j, H:i'),
            ];
        }

        $queuedJobs = [];
        $reservedJobs = [];
        $activeRuns = [];
        $outstandingResults = [];

        foreach ($range['keys'] as $key) {
            $row = $bucketRows[$key];
            $queuedJobs[] = (int) ($row->queued_jobs ?? 0);
            $reservedJobs[] = (int) ($row->reserved_jobs ?? 0);
            $activeRuns[] = (int) ($row->active_runs ?? 0);
            $outstandingResults[] = (int) ($row->outstanding_results ?? 0);
        }

        return [
            'latest' => $latest,
            'chart' => [
                'labels' => $range['labels'],
                'queued_jobs' => $queuedJobs,
                'reserved_jobs' => $reservedJobs,
                'active_runs' => $activeRuns,
                'outstanding_results' => $outstandingResults,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildBucketRange(string $bucket, int $hours): array
    {
        $now = now();
        $start = $bucket === 'hour'
            ? $now->copy()->subHours($hours - 1)->startOfHour()
            : $now->copy()->subDays((int) ceil($hours / 24) - 1)->startOfDay();

        $end = $bucket === 'hour'
            ? $now->copy()->startOfHour()
            : $now->copy()->startOfDay();

        $keys = [];
        $labels = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $keys[] = $this->bucketKey($cursor, $bucket);
            $labels[] = $bucket === 'hour' ? $cursor->format('M j H:i') : $cursor->format('M j');
            $cursor = $bucket === 'hour' ? $cursor->addHour() : $cursor->addDay();
        }

        return [
            'bucket' => $bucket,
            'hours' => $hours,
            'start' => $start,
            'keys' => $keys,
            'labels' => $labels,
        ];
    }

    /**
     * @return array{key:string,label:string,hours:int,bucket:string}
     */
    private function resolveWindow(string $window): array
    {
        $key = array_key_exists($window, self::WINDOW_PRESETS) ? $window : '30d';
        $config = self::WINDOW_PRESETS[$key];

        return [
            'key' => $key,
            'label' => (string) $config['label'],
            'hours' => (int) $config['hours'],
            'bucket' => (string) $config['bucket'],
        ];
    }

    private function bucketKey(CarbonInterface $date, string $bucket): string
    {
        return $bucket === 'hour'
            ? $date->copy()->startOfHour()->format('Y-m-d H:00:00')
            : $date->copy()->startOfDay()->toDateString();
    }

    private function percentile(array $values, int $percentile): ?int
    {
        $values = array_values(array_filter($values, static fn (mixed $value): bool => is_numeric($value)));
        if ($values === []) {
            return null;
        }

        sort($values);
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;
        $index = max(0, min($index, count($values) - 1));

        return (int) round((float) $values[$index]);
    }

    private function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return 'n/a';
        }

        if ($seconds < 60) {
            return $seconds . 's';
        }

        if ($seconds < 3600) {
            return round($seconds / 60, 1) . 'm';
        }

        return round($seconds / 3600, 1) . 'h';
    }

    private function parseDate(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value);
    }

    private function settledAt(string $status, mixed $completedAt, mixed $updatedAt): ?CarbonInterface
    {
        if ($status === 'completed') {
            return $this->parseDate($completedAt) ?? $this->parseDate($updatedAt);
        }

        return $this->parseDate($updatedAt);
    }
}
