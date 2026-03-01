<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RunScanRequest;
use App\Services\Scanner\ProxyManagerService;
use App\Services\Scanner\ScannerEngineService;
use App\Support\ScanRunStore;

final class ScanController extends Controller
{
    public function index(ScannerEngineService $engine)
    {
        return view('scanner.index', [
            'results' => [],
            'summary' => null,
            'input' => ['mode' => 'username'],
            'moduleCatalog' => $engine->listModules('username'),
        ]);
    }

    public function run(RunScanRequest $request, ScannerEngineService $engine, ProxyManagerService $proxyManager, ScanRunStore $store)
    {
        $validated = $request->validated();

        if (!empty($validated['proxy_list'])) {
            $proxyManager->loadFromText($validated['proxy_list']);
        }

        $moduleKeys = null;
        if (!empty($validated['module_keys'])) {
            $moduleKeys = array_values(array_filter(array_map('trim', explode(',', $validated['module_keys']))));
        }

        $results = $engine->scan(
            target: $validated['target'],
            mode: $validated['mode'],
            category: $validated['category'] ?? null,
            moduleKeys: $moduleKeys,
            options: [
                'use_proxy' => (bool) ($validated['use_proxy'] ?? false),
            ]
        );

        $normalized = array_map(static fn ($r) => $r->toArray(), $results);

        // Persist single scans as first-class runs for history/filter/export.
        $runId = $store->createRun($validated['mode'], [$validated['target']]);
        $store->appendResults($runId, $normalized);

        return view('scanner.index', [
            'results' => $normalized,
            'summary' => [
                'run_id' => $runId,
                'total' => count($normalized),
                'success' => count(array_filter($normalized, static fn ($r) => in_array($r['status'], ['Available', 'Taken', 'Registered', 'Not Registered'], true))),
                'errors' => count(array_filter($normalized, static fn ($r) => ($r['status'] ?? '') === 'Error')),
            ],
            'input' => $validated,
            'moduleCatalog' => $engine->listModules($validated['mode']),
        ]);
    }
}
