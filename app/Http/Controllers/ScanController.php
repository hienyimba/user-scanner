<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RunScanRequest;
use App\Services\Scanner\QueuedScanService;
use App\Services\Scanner\ScannerEngineService;
use App\Support\ScanRunStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class ScanController extends Controller
{
    public function index(Request $request, ScannerEngineService $engine, ScanRunStore $store)
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
            'summary' => $currentRun ? $this->summaryFromRun($currentRun, $results) : null,
            'currentRun' => $currentRun,
            'input' => $input,
            'moduleCatalog' => $engine->listModules($input['mode'], (bool) ($input['no_nsfw'] ?? false)),
            'categoryCatalog' => $engine->listCategories($input['mode'], (bool) ($input['no_nsfw'] ?? false)),
        ]);
    }

    public function run(
        RunScanRequest $request,
        ScannerEngineService $engine,
        QueuedScanService $queuedRuns,
        ScanRunStore $store,
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

        $allResults = [];
        foreach ($plan['expanded_targets'] as $targetIndex => $expandedTarget) {
            if ($targetIndex !== 0 && (float) ($options['delay'] ?? 0) > 0) {
                usleep((int) (((float) $options['delay']) * 1_000_000));
            }

            foreach ($plan['validators'] as $validatorIndex => $validator) {
                $allResults[] = $engine->runPlannedValidator(
                    mode: $validated['mode'],
                    validatorKey: $validator->key(),
                    target: $expandedTarget,
                    options: $options,
                )->toArray() + [
                    '__target_index' => $targetIndex,
                    '__validator_index' => $validatorIndex,
                ];
            }
        }

        $runId = $store->createRun(
            mode: $validated['mode'],
            targets: [$validated['target']],
            options: $options + [
                'category' => $validated['category'] ?? null,
                'module_keys' => $validated['module_keys'] ?? [],
            ],
            expandedTargets: $plan['expanded_targets'],
            validatorCount: count($plan['validators']),
            expectedResults: count($allResults),
            selectedValidatorKeys: $plan['validator_keys'],
        );

        foreach ($allResults as $result) {
            $store->markJobStarted($runId);
            $store->appendResult(
                $runId,
                $result,
                (int) ($result['__target_index'] ?? 0),
                (int) ($result['__validator_index'] ?? 0),
            );
        }

        return redirect()->route('scanner.index', [
            'mode' => $validated['mode'],
            'run_id' => $runId,
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

    /**
     * @param array<string, mixed> $run
     * @param array<int, array<string, mixed>> $results
     * @return array<string, mixed>
     */
    private function summaryFromRun(array $run, array $results): array
    {
        $mode = (string) ($run['mode'] ?? 'username');

        return [
            'run_id' => $run['id'],
            'total' => count($results),
            'success' => count(array_filter($results, fn (array $r): bool => in_array($r['status'] ?? '', $mode === 'email' ? ['Registered'] : ['Found'], true))),
            'errors' => count(array_filter($results, static fn (array $r): bool => ($r['status'] ?? '') === 'Error')),
            'skipped' => count(array_filter($results, static fn (array $r): bool => ($r['status'] ?? '') === 'Skipped')),
            'meta' => [
                'expanded_targets' => $run['expanded_targets'] ?? [],
                'modules_scanned' => $run['validator_count'] ?? 0,
                'expected_results' => $run['expected_results'] ?? 0,
            ],
        ];
    }
}
