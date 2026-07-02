<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Scanner\QueuedScanService;
use App\Support\ScanRunStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class RunController extends Controller
{
    public function create(Request $request, QueuedScanService $queuedRuns): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:username,email'],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            'module_keys' => ['nullable', 'array'],
            'module_keys.*' => ['string', 'max:100'],
            'use_proxy' => ['nullable', 'boolean'],
            'validate_proxies' => ['nullable', 'boolean'],
            'allow_loud' => ['nullable', 'boolean'],
            'no_nsfw' => ['nullable', 'boolean'],
            'only_found' => ['nullable', 'boolean'],
            'verbose' => ['nullable', 'boolean'],
            'delay' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'stop' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'proxy_list' => ['nullable', 'string', 'max:20000'],
        ]);

        try {
            $run = $queuedRuns->startRun(
                mode: $data['mode'],
                targets: $data['targets'],
                category: $data['category'] ?? null,
                moduleKeys: $data['module_keys'] ?? null,
                options: $data,
            );
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'run_id' => $run['run_id']], 202);
    }

    public function show(string $runId, Request $request, ScanRunStore $store): JsonResponse
    {
        $run = $store->getRun($runId);
        if (!$run) {
            return response()->json(['ok' => false, 'error' => 'Run not found'], 404);
        }

        $status = $request->query('status');
        $category = $request->query('category');
        $filtered = $store->filteredResults($runId, is_string($status) ? $status : null, is_string($category) ? $category : null);

        return response()->json([
            'ok' => true,
            'run' => [
                'id' => $run['id'],
                'mode' => $run['mode'],
                'status' => $run['status'],
                'total' => $run['total'],
                'processed' => $run['processed'],
                'validator_count' => $run['validator_count'],
                'target_count' => $run['target_count'],
                'expected_results' => $run['expected_results'],
                'queued_jobs' => $run['queued_jobs'],
                'running_jobs' => $run['running_jobs'],
                'completed_jobs' => $run['completed_jobs'],
                'progress' => ($run['total'] ?? 0) > 0 ? round((($run['processed'] ?? 0) / $run['total']) * 100, 2) : 0,
                'created_at' => $run['created_at'] ?? null,
                'updated_at' => $run['updated_at'] ?? null,
                'completed_at' => $run['completed_at'] ?? null,
                'error' => $run['error'] ?? null,
                'options' => $run['options'] ?? [],
                'expanded_targets' => $run['expanded_targets'] ?? [],
            ],
            'results' => $filtered,
        ]);
    }

    public function index(Request $request, ScanRunStore $store): JsonResponse
    {
        $status = $request->query('status');

        return response()->json(['ok' => true, 'runs' => $store->listRuns(is_string($status) ? $status : null)]);
    }

    public function export(string $runId, string $format, Request $request, ScanRunStore $store)
    {
        $format = strtolower($format);
        if (!in_array($format, ['json', 'csv'], true)) {
            return response()->json(['ok' => false, 'error' => 'Unsupported export format'], 422);
        }

        $status = $request->query('status');
        $category = $request->query('category');
        $rows = $store->filteredResults($runId, is_string($status) ? $status : null, is_string($category) ? $category : null);

        if ($format === 'json') {
            return response()->json(['ok' => true, 'run_id' => $runId, 'count' => count($rows), 'results' => $rows]);
        }

        $header = ['target', 'category', 'site_name', 'url', 'status', 'reason', 'extra', 'mode', 'key'];
        $lines = [implode(',', $header)];

        foreach ($rows as $row) {
            $line = [
                $row['target'] ?? '',
                $row['category'] ?? '',
                $row['site_name'] ?? '',
                $row['url'] ?? '',
                $row['status'] ?? '',
                $row['reason'] ?? '',
                $row['extra'] ?? '',
                $row['mode'] ?? '',
                $row['key'] ?? '',
            ];
            $lines[] = implode(',', array_map(static fn (string $v): string => '"' . str_replace('"', '""', $v) . '"', $line));
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=scan_{$runId}.csv",
        ]);
    }
}
