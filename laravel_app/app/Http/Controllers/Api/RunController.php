<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunScanJob;
use App\Support\ScanRunStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RunController extends Controller
{
    public function create(Request $request, ScanRunStore $store): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:username,email'],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            'module_keys' => ['nullable', 'string', 'max:500'],
            'use_proxy' => ['nullable', 'boolean'],
            'proxy_list' => ['nullable', 'string', 'max:10000'],
        ]);

        $targets = array_values(array_unique(array_map('trim', $data['targets'])));
        $runId = $store->createRun($data['mode'], $targets);

        $moduleKeys = null;
        if (!empty($data['module_keys'])) {
            $moduleKeys = array_values(array_filter(array_map('trim', explode(',', $data['module_keys']))));
        }

        foreach ($targets as $target) {
            RunScanJob::dispatch(
                runId: $runId,
                mode: $data['mode'],
                target: $target,
                category: $data['category'] ?? null,
                moduleKeys: $moduleKeys,
                useProxy: (bool) ($data['use_proxy'] ?? false),
                proxyList: $data['proxy_list'] ?? null,
            );
        }

        return response()->json(['ok' => true, 'run_id' => $runId], 202);
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
                'progress' => ($run['total'] ?? 0) > 0 ? round((($run['processed'] ?? 0) / $run['total']) * 100, 2) : 0,
                'created_at' => $run['created_at'] ?? null,
                'updated_at' => $run['updated_at'] ?? null,
                'error' => $run['error'] ?? null,
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

        $header = ['target', 'category', 'site_name', 'url', 'status', 'reason'];
        $lines = [implode(',', $header)];

        foreach ($rows as $row) {
            $line = [
                $row['target'] ?? '',
                $row['category'] ?? '',
                $row['site_name'] ?? '',
                $row['url'] ?? '',
                $row['status'] ?? '',
                $row['reason'] ?? '',
            ];
            $lines[] = implode(',', array_map(static fn (string $v): string => '"' . str_replace('"', '""', $v) . '"', $line));
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=scan_{$runId}.csv",
        ]);
    }
}
