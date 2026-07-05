<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use App\Jobs\RunValidatorJob;
use App\Support\ScanRunStore;
use Illuminate\Support\Arr;
use RuntimeException;

final class QueuedScanService
{
    public function __construct(
        private readonly ScannerEngineService $engine,
        private readonly ProxyManagerService $proxyManager,
        private readonly ScanRunStore $store,
    ) {
    }

    /**
     * @param array<int, string> $targets
     * @param array<string, mixed> $options
     * @return array{run_id:string, expected_results:int, validator_count:int, expanded_targets: array<int, string>}
     */
    public function startRun(string $mode, array $targets, ?string $category = null, ?array $moduleKeys = null, array $options = []): array
    {
        $targets = array_values(array_unique(array_filter(array_map(
            static fn (string $target): string => trim($target),
            $targets
        ), static fn (string $target): bool => $target !== '')));

        $preparedOptions = $this->prepareOptions($options);
        $plans = [];
        $expandedTargets = [];
        $selectedValidatorKeys = [];
        $expectedResults = 0;
        $validatorCount = 0;

        foreach ($targets as $target) {
            $plan = $this->engine->planScan($target, $mode, $category, $moduleKeys, $preparedOptions);
            $plans[] = $plan;
            $expandedTargets = array_merge($expandedTargets, $plan['expanded_targets']);
            $selectedValidatorKeys = $plan['validator_keys'];
            $validatorCount = count($plan['validators']);
            $expectedResults += $plan['expected_results'];
        }

        $maxExpectedResults = (int) config('scanner.async.max_expected_results', 5000);
        if ($expectedResults > $maxExpectedResults) {
            throw new RuntimeException(sprintf(
                'This scan would enqueue %d validator jobs, which exceeds the configured limit of %d.',
                $expectedResults,
                $maxExpectedResults
            ));
        }

        $runId = $this->store->createRun(
            mode: $mode,
            targets: $targets,
            options: $preparedOptions + [
                'category' => $category,
                'module_keys' => $moduleKeys ?? [],
            ],
            expandedTargets: $expandedTargets,
            validatorCount: $validatorCount,
            expectedResults: $expectedResults,
            selectedValidatorKeys: $selectedValidatorKeys,
        );

        try {
            $globalTargetIndex = 0;
            foreach ($plans as $plan) {
                foreach ($plan['expanded_targets'] as $targetOffset => $expandedTarget) {
                    foreach ($plan['validators'] as $validatorIndex => $validator) {
                        $job = new RunValidatorJob(
                            runId: $runId,
                            mode: $mode,
                            validatorKey: $validator->key(),
                            validatorMeta: [
                                'category' => strtolower($validator->category()),
                                'site_name' => $validator->siteName(),
                                'url' => $validator->siteUrl(),
                            ],
                            target: $expandedTarget,
                            targetIndex: $globalTargetIndex,
                            validatorIndex: $validatorIndex,
                            options: $preparedOptions,
                            proxyOffset: $globalTargetIndex + $validatorIndex,
                        );

                        $delaySeconds = (float) ($preparedOptions['delay'] ?? 0);
                        if ($delaySeconds > 0 && $targetOffset > 0) {
                            $job->delay(now()->addMilliseconds((int) round($globalTargetIndex * $delaySeconds * 1000)));
                        }

                        dispatch($job->onQueue((string) config('scanner.async.queue', 'scanner')));
                    }

                    $globalTargetIndex++;
                }
            }
        } catch (\Throwable $e) {
            $this->store->failRun($runId, $e->getMessage());
            throw $e;
        }

        return [
            'run_id' => $runId,
            'expected_results' => $expectedResults,
            'validator_count' => $validatorCount,
            'expanded_targets' => $expandedTargets,
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function prepareOptions(array $options): array
    {
        $prepared = $options;
        $disableProxy = (bool) Arr::get($prepared, 'disable_proxy', false);
        $defaultProxyList = implode(PHP_EOL, config('scanner.proxy_list', []));

        if (!$disableProxy && empty($prepared['proxy_list']) && $defaultProxyList !== '') {
            $prepared['proxy_list'] = $defaultProxyList;
        }

        if (!$disableProxy && !empty($prepared['proxy_list'])) {
            $this->proxyManager->loadFromText((string) $prepared['proxy_list']);
            if (!empty($prepared['validate_proxies'])) {
                $this->proxyManager->validateWorking();
                $prepared['proxy_list'] = implode(PHP_EOL, $this->proxyManager->all());
            }

            $prepared['use_proxy'] = !empty($prepared['use_proxy']) || trim((string) $prepared['proxy_list']) !== '';
        }

        if ($disableProxy) {
            $prepared['use_proxy'] = false;
            $prepared['proxy'] = null;
            $prepared['proxy_list'] = '';
        }

        $prepared['allow_loud'] = (bool) Arr::get($prepared, 'allow_loud', false);
        $prepared['no_nsfw'] = (bool) Arr::get($prepared, 'no_nsfw', false);
        $prepared['only_found'] = (bool) Arr::get($prepared, 'only_found', false);
        $prepared['verbose'] = (bool) Arr::get($prepared, 'verbose', false);
        $prepared['validate_proxies'] = (bool) Arr::get($prepared, 'validate_proxies', false);
        $prepared['disable_proxy'] = $disableProxy;
        $prepared['use_proxy'] = (bool) Arr::get($prepared, 'use_proxy', false);
        $prepared['delay'] = (float) Arr::get($prepared, 'delay', 0);
        $prepared['stop'] = (int) Arr::get($prepared, 'stop', 100);

        return $prepared;
    }
}
