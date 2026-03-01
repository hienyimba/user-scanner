<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ScanStatus;
use App\Http\Requests\StoreScanRequest;
use App\Jobs\RunScanBatchJob;
use App\Models\ScanBatch;
use App\Services\Scanning\Exports\ScanExportService;
use Illuminate\Http\Response;

class ScanController extends Controller
{
    public function create()
    {
        return view('scans.create');
    }

    public function store(StoreScanRequest $request)
    {
        $data = $request->validated();
        $targets = $data['targets'] ?? [$data['target']];

        $firstBatch = null;

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
            $firstBatch ??= $batch;
        }

        return redirect()->route('scans.show', $firstBatch)->with('status', sprintf('%d scan(s) queued successfully.', count($targets)));
    }

    public function show(ScanBatch $scan)
    {
        $scan->load('results');

        return view('scans.show', ['scan' => $scan]);
    }

    public function cancel(ScanBatch $scan)
    {
        if (in_array($scan->status?->value ?? (string) $scan->status, ['queued', 'running'], true)) {
            $scan->update(['status' => ScanStatus::Cancelled]);
        }

        return redirect()->route('scans.show', $scan)->with('status', 'Scan cancelled.');
    }

    public function export(ScanBatch $scan, string $format, ScanExportService $exportService): Response
    {
        $filename = sprintf('scan_%d.%s', $scan->id, $format);

        if ($format === 'json') {
            return response($exportService->toJson($scan), 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ]);
        }

        abort_unless($format === 'csv', 404);

        return response($exportService->toCsv($scan), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }
}
