<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ScanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScanRequest;
use App\Jobs\RunScanBatchJob;
use App\Models\ScanBatch;
use App\Services\Scanning\Exports\ScanExportService;
use Illuminate\Http\JsonResponse;

class ScanController extends Controller
{
    public function store(StoreScanRequest $request): JsonResponse
    {
        $data = $request->validated();
        $targets = $data['targets'] ?? [$data['target']];
        $created = [];

        foreach ($targets as $target) {
            $batch = ScanBatch::create([
                'user_id' => optional($request->user())->id,
                'type' => $data['type'],
                'target' => $target,
                'status' => ScanStatus::Queued,
                'options' => [
                    'module' => $data['module'] ?? null,
                    'category' => $data['category'] ?? null,
                    'proxy_profile' => $data['proxy_profile'] ?? null,
                    'verbose' => (bool) ($data['verbose'] ?? false),
                    'retry_limit' => $data['retry_limit'] ?? config('scanner.queue.default_retry_limit', 3),
                    'timeout_seconds' => $data['timeout_seconds'] ?? config('scanner.queue.default_timeout_seconds', 20),
                ],
                'total_items' => 0,
                'processed_items' => 0,
                'error_count' => 0,
            ]);

            RunScanBatchJob::dispatch($batch->id);
            $created[] = ['id' => $batch->id, 'target' => $batch->target, 'status' => $batch->status, 'type' => $batch->type];
        }

        return response()->json(['queued' => $created], 202);
    }

    public function show(ScanBatch $scan): JsonResponse
    {
        $scan->load('results');

        return response()->json($scan);
    }

    public function cancel(ScanBatch $scan): JsonResponse
    {
        if (in_array($scan->status?->value ?? (string) $scan->status, ['queued', 'running'], true)) {
            $scan->update(['status' => ScanStatus::Cancelled]);
        }

        return response()->json(['id' => $scan->id, 'status' => $scan->status]);
    }

    public function export(ScanBatch $scan, string $format, ScanExportService $exportService): JsonResponse
    {
        if ($format === 'json') {
            return response()->json(json_decode($exportService->toJson($scan), true));
        }

        abort_unless($format === 'csv', 404);

        return response()->json(['csv' => base64_encode($exportService->toCsv($scan))]);
    }
}
