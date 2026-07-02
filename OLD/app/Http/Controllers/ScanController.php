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
        $mode = 'username';

        return view('scanner.index', [
            'results' => [],
            'resultsByCategory' => [],
            'summary' => null,
            'input' => [
                'mode' => $mode,
                'module_keys' => [],
                'stop' => 100,
                'delay' => 0,
            ],
            'moduleCatalog' => $engine->listModules($mode),
            'categoryCatalog' => $engine->listCategories($mode),
        ]);
    }

    public function run(RunScanRequest $request, ScannerEngineService $engine, ProxyManagerService $proxyManager, ScanRunStore $store)
    {
        $validated = $request->validated();

        if (!empty($validated['proxy_list'])) {
            $proxyManager->loadFromText($validated['proxy_list']);
            if (!empty($validated['validate_proxies'])) {
                $proxyManager->validateWorking();
            }
        }

        $scan = $engine->scanWithMeta(
            target: $validated['target'],
            mode: $validated['mode'],
            category: $validated['category'] ?? null,
            moduleKeys: $validated['module_keys'] ?? null,
            options: [
                'use_proxy' => (bool) ($validated['use_proxy'] ?? false),
                'validate_proxies' => (bool) ($validated['validate_proxies'] ?? false),
                'allow_loud' => (bool) ($validated['allow_loud'] ?? false),
                'no_nsfw' => (bool) ($validated['no_nsfw'] ?? false),
                'only_found' => (bool) ($validated['only_found'] ?? false),
                'verbose' => (bool) ($validated['verbose'] ?? false),
                'delay' => (float) ($validated['delay'] ?? 0),
                'stop' => (int) ($validated['stop'] ?? 100),
            ]
        );

        $normalized = array_map(static fn ($r) => $r->toArray(), $scan['results']);

        $runId = $store->createRun(
            mode: $validated['mode'],
            targets: [$validated['target']],
            options: $validated,
            expandedTargets: $scan['meta']['expanded_targets'] ?? [$validated['target']],
        );
        $store->appendResults($runId, $normalized, $scan['meta']['expanded_targets'] ?? [$validated['target']]);

        return view('scanner.index', [
            'results' => $normalized,
            'resultsByCategory' => $this->groupByCategory($normalized),
            'summary' => [
                'run_id' => $runId,
                'total' => count($normalized),
                'success' => count(array_filter($normalized, fn (array $r): bool => in_array($r['status'] ?? '', $validated['mode'] === 'email' ? ['Registered'] : ['Found'], true))),
                'errors' => count(array_filter($normalized, static fn (array $r): bool => ($r['status'] ?? '') === 'Error')),
                'skipped' => count(array_filter($normalized, static fn (array $r): bool => ($r['status'] ?? '') === 'Skipped')),
                'meta' => $scan['meta'],
            ],
            'input' => $validated,
            'moduleCatalog' => $engine->listModules($validated['mode'], (bool) ($validated['no_nsfw'] ?? false)),
            'categoryCatalog' => $engine->listCategories($validated['mode'], (bool) ($validated['no_nsfw'] ?? false)),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupByCategory(array $results): array
    {
        $grouped = [];
        foreach ($results as $result) {
            $category = (string) ($result['category'] ?? 'other');
            $grouped[$category][] = $result;
        }

        ksort($grouped);

        return $grouped;
    }
}
