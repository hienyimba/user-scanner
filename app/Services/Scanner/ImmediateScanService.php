<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use App\Support\ScanRunStore;

final class ImmediateScanService
{
    public function __construct(
        private readonly ScannerEngineService $engine,
        private readonly ScanRunStore $store,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     * @return array{run_id:string, expected_results:int, validator_count:int, expanded_targets: array<int, string>}
     */
    public function run(string $target, string $mode, ?string $category = null, ?array $moduleKeys = null, array $options = []): array
    {
        $plan = $this->engine->planScan(
            target: $target,
            mode: $mode,
            category: $category,
            moduleKeys: $moduleKeys,
            options: $options,
        );

        $results = [];
        foreach ($plan['expanded_targets'] as $targetIndex => $expandedTarget) {
            if ($targetIndex !== 0 && (float) ($options['delay'] ?? 0) > 0) {
                usleep((int) (((float) $options['delay']) * 1_000_000));
            }

            foreach ($plan['validators'] as $validatorIndex => $validator) {
                $results[] = $this->engine->runPlannedValidator(
                    mode: $mode,
                    validatorKey: $validator->key(),
                    target: $expandedTarget,
                    options: $options,
                )->toArray() + [
                    '__target_index' => $targetIndex,
                    '__validator_index' => $validatorIndex,
                ];
            }
        }

        $runId = $this->store->createRun(
            mode: $mode,
            targets: [$target],
            options: $options + [
                'category' => $category,
                'module_keys' => $moduleKeys ?? [],
            ],
            expandedTargets: $plan['expanded_targets'],
            validatorCount: count($plan['validators']),
            expectedResults: count($results),
            selectedValidatorKeys: $plan['validator_keys'],
        );

        foreach ($results as $result) {
            $this->store->markJobStarted($runId);
            $this->store->appendResult(
                $runId,
                $result,
                (int) ($result['__target_index'] ?? 0),
                (int) ($result['__validator_index'] ?? 0),
            );
        }

        return [
            'run_id' => $runId,
            'expected_results' => count($results),
            'validator_count' => count($plan['validators']),
            'expanded_targets' => $plan['expanded_targets'],
        ];
    }
}
