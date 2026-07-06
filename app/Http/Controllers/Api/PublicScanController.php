<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicScanRequest;
use App\Services\Scanner\MetadataCapabilityService;
use App\Services\Scanner\QueuedScanService;
use App\Services\Scanner\ScannerEngineService;
use App\Support\ModuleCatalogPresenter;
use App\Support\ScanRunPresenter;
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

    public function show(string $runId, ScanRunStore $store, ScanRunPresenter $presenter): JsonResponse
    {
        $run = $store->getRun($runId);
        if (!$run) {
            return response()->json(['ok' => false, 'error' => 'Run not found'], 404);
        }

        $showHits = (bool) ($run['options']['show_hits'] ?? $run['options']['only_found'] ?? false);
        $results = $store->filteredResults($runId, null, null, $showHits);

        return response()->json([
            'ok' => true,
            'run' => $presenter->publicApiRun($run, $showHits),
            'results' => $results,
        ]);
    }

    public function final(string $runId, ScanRunStore $store, ScanRunPresenter $presenter): JsonResponse
    {
        $run = $store->getRun($runId);
        if (!$run) {
            return response()->json(['ok' => false, 'error' => 'Run not found'], 404);
        }

        $showHits = (bool) ($run['options']['show_hits'] ?? $run['options']['only_found'] ?? false);
        $payload = [
            'ok' => true,
            'ready' => false,
            'run' => $presenter->publicApiRun($run, $showHits),
        ];

        if (($run['status'] ?? null) === 'completed') {
            return response()->json([
                ...$payload,
                'ready' => true,
                'results' => $store->filteredResults($runId, null, null, $showHits),
            ]);
        }

        if (($run['status'] ?? null) === 'failed') {
            return response()->json([
                'ok' => false,
                'ready' => false,
                'error' => $run['error'] ?? 'Run failed',
                'run' => $presenter->publicApiRun($run, $showHits),
            ], 409);
        }

        return response()->json($payload, 202);
    }

    public function modules(
        string $mode,
        ScannerEngineService $engine,
        MetadataCapabilityService $metadataCapability,
        ModuleCatalogPresenter $presenter,
    ): JsonResponse
    {
        abort_unless(in_array($mode, ['username', 'email'], true), 404);

        return response()->json($presenter->apiPayload($mode, $engine, $metadataCapability));
    }
}
