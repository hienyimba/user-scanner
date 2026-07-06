<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RunBatchRequest;
use App\Services\Scanner\QueuedScanService;
use App\Support\ScanRunPresenter;
use App\Support\ScanRunStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class RunController extends Controller
{
    public function create(RunBatchRequest $request, QueuedScanService $queuedRuns): JsonResponse
    {
        $data = $request->validated();

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

    public function show(string $runId, Request $request, ScanRunStore $store, ScanRunPresenter $presenter): JsonResponse
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
            'run' => $presenter->adminApiRun($run),
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
