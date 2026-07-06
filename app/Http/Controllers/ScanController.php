<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RunScanRequest;
use App\Services\Scanner\ImmediateScanService;
use App\Services\Scanner\QueuedScanService;
use App\Services\Scanner\ScannerEngineService;
use App\Support\ScanRunPresenter;
use App\Support\ScanRunStore;
use Illuminate\Http\Request;
use RuntimeException;

final class ScanController extends Controller
{
    public function index(Request $request, ScannerEngineService $engine, ScanRunStore $store, ScanRunPresenter $presenter)
    {
        $mode = (string) $request->query('mode', 'username');
        $mode = in_array($mode, ['username', 'email'], true) ? $mode : 'username';
        $runId = is_string($request->query('run_id')) ? $request->query('run_id') : null;
        $currentRun = $runId ? $store->getRun($runId) : null;
        $results = $runId ? $store->filteredResults($runId) : [];
        $defaultProxyList = implode(PHP_EOL, config('scanner.proxy_list', []));

        $input = [
            'mode' => $mode,
            'module_keys' => [],
            'stop' => 100,
            'delay' => 0,
            'proxy_list' => $defaultProxyList,
            'use_proxy' => $defaultProxyList !== '',
        ];

        if ($currentRun) {
            $input = array_merge($input, $currentRun['options'] ?? [], ['mode' => $currentRun['mode'] ?? $mode]);
        }

        return view('scanner.index', [
            'results' => $results,
            'resultsByCategory' => $this->groupByCategory($results),
            'summary' => $currentRun ? $presenter->webSummary($currentRun, $results) : null,
            'currentRun' => $currentRun,
            'input' => $input,
            'moduleCatalog' => $engine->listModules($input['mode'], (bool) ($input['no_nsfw'] ?? false)),
            'categoryCatalog' => $engine->listCategories($input['mode'], (bool) ($input['no_nsfw'] ?? false)),
        ]);
    }

    public function run(
        RunScanRequest $request,
        ScannerEngineService $engine,
        ImmediateScanService $immediateScans,
        QueuedScanService $queuedRuns,
    ) {
        $validated = $request->validated();
        $options = $queuedRuns->prepareOptions($validated);
        $plan = $engine->planScan(
            target: $validated['target'],
            mode: $validated['mode'],
            category: $validated['category'] ?? null,
            moduleKeys: $validated['module_keys'] ?? null,
            options: $options,
        );

        if ($this->shouldQueueRun($validated, $plan)) {
            try {
                $run = $queuedRuns->startRun(
                    mode: $validated['mode'],
                    targets: [$validated['target']],
                    category: $validated['category'] ?? null,
                    moduleKeys: $validated['module_keys'] ?? null,
                    options: $validated,
                );
            } catch (RuntimeException $e) {
                return back()->withErrors(['target' => $e->getMessage()])->withInput();
            }

            return redirect()->route('scanner.index', [
                'mode' => $validated['mode'],
                'run_id' => $run['run_id'],
            ]);
        }

        $run = $immediateScans->run(
            target: $validated['target'],
            mode: $validated['mode'],
            category: $validated['category'] ?? null,
            moduleKeys: $validated['module_keys'] ?? null,
            options: $options,
        );

        return redirect()->route('scanner.index', [
            'mode' => $validated['mode'],
            'run_id' => $run['run_id'],
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $plan
     */
    private function shouldQueueRun(array $validated, array $plan): bool
    {
        if (($validated['category'] ?? null) !== null) {
            return true;
        }

        return (int) ($plan['expected_results'] ?? 0) > (int) config('scanner.async.sync_result_threshold', 12);
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
