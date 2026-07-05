<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicScanRequest;
use App\Services\Scanner\MetadataCapabilityService;
use App\Services\Scanner\QueuedScanService;
use App\Services\Scanner\ScannerEngineService;
use App\Support\ScanRunStore;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class PublicScanController extends Controller
{
    public function create(PublicScanRequest $request, QueuedScanService $queuedRuns): JsonResponse
    {
        $data = $request->validated();
        $options = [
            'use_proxy' => (bool) ($data['use_proxy'] ?? false),
            'show_hits' => (bool) ($data['show_hits'] ?? false),
            'only_found' => (bool) ($data['show_hits'] ?? false),
            'category' => $data['category'] ?? null,
        ];

        try {
            $run = $queuedRuns->startRun(
                mode: $data['mode'],
                targets: [$data['target']],
                category: $data['category'] ?? null,
                moduleKeys: null,
                options: $options,
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'run_id' => $run['run_id'],
            'status' => 'queued',
            'mode' => $data['mode'],
            'category' => $data['category'] ?? null,
            'target' => $data['target'],
        ], 202);
    }

    public function show(string $runId, ScanRunStore $store): JsonResponse
    {
        $run = $store->getRun($runId);
        if (!$run) {
            return response()->json(['ok' => false, 'error' => 'Run not found'], 404);
        }

        $showHits = (bool) ($run['options']['show_hits'] ?? $run['options']['only_found'] ?? false);
        $results = $store->filteredResults($runId, null, null, $showHits);

        return response()->json([
            'ok' => true,
            'run' => [
                'id' => $run['id'],
                'mode' => $run['mode'],
                'status' => $run['status'],
                'progress' => ($run['total'] ?? 0) > 0 ? round((($run['processed'] ?? 0) / $run['total']) * 100, 2) : 0,
                'processed' => $run['processed'],
                'total' => $run['total'],
                'error' => $run['error'],
                'created_at' => $run['created_at'],
                'updated_at' => $run['updated_at'],
                'completed_at' => $run['completed_at'],
                'target' => $run['targets'][0] ?? null,
                'category' => $run['options']['category'] ?? null,
                'show_hits' => $showHits,
            ],
            'results' => $results,
        ]);
    }

    public function modules(string $mode, ScannerEngineService $engine, MetadataCapabilityService $metadataCapability): JsonResponse
    {
        abort_unless(in_array($mode, ['username', 'email'], true), 404);

        return response()->json([
            'ok' => true,
            'mode' => $mode,
            'metadata_summary' => $metadataCapability->summary(),
            'categories' => $engine->listCategories($mode),
            'modules' => $engine->listModules($mode),
        ]);
    }
}
