<?php

declare(strict_types=1);

namespace App\Services\Scanner;

final class MetadataValidationReportSupport
{
    /**
     * @return array<string, mixed>
     */
    public static function emptyAudit(string $mode): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'mode' => $mode,
            'summary' => [
                'audited_results' => 0,
                'found_results' => 0,
                'not_found_results' => 0,
                'error_results' => 0,
                'results_with_metadata_level_3_plus' => 0,
                'results_with_metadata_level_4' => 0,
                'found_results_below_documented_level' => 0,
            ],
            'results' => [],
        ];
    }

    /**
     * @param array<string, mixed> $audit
     * @param array<string, string> $labelsByResolved
     * @return array<string, mixed>
     */
    public static function aliasAuditTargets(array $audit, array $labelsByResolved): array
    {
        if ($labelsByResolved === []) {
            return $audit;
        }

        $audit['targets'] = array_values(array_map(
            fn (string $target): string => $labelsByResolved[$target] ?? $target,
            array_values(array_map('strval', is_array($audit['targets'] ?? null) ? $audit['targets'] : [])),
        ));

        $audit['plans'] = array_map(function (mixed $plan) use ($labelsByResolved): mixed {
            if (!is_array($plan)) {
                return $plan;
            }

            if (is_string($plan['target'] ?? null)) {
                $plan['target'] = $labelsByResolved[$plan['target']] ?? $plan['target'];
            }

            if (is_array($plan['expanded_targets'] ?? null)) {
                $plan['expanded_targets'] = array_values(array_map(
                    fn (string $target): string => $labelsByResolved[$target] ?? $target,
                    array_values(array_map('strval', $plan['expanded_targets'])),
                ));
            }

            return $plan;
        }, is_array($audit['plans'] ?? null) ? $audit['plans'] : []);

        $audit['results'] = array_map(function (mixed $result) use ($labelsByResolved): mixed {
            if (!is_array($result)) {
                return $result;
            }

            if (is_string($result['target'] ?? null)) {
                $result['target'] = $labelsByResolved[$result['target']] ?? $result['target'];
            }

            if (is_array($result['normalized'] ?? null) && is_string($result['normalized']['target'] ?? null)) {
                $result['normalized']['target'] = $labelsByResolved[$result['normalized']['target']] ?? $result['normalized']['target'];
            }

            return $result;
        }, is_array($audit['results'] ?? null) ? $audit['results'] : []);

        return $audit;
    }

    /**
     * @param array<string, mixed> $audit
     * @param array<int, string> $unresolvedTargets
     * @return array{
     *   results: array<int, array<string, mixed>>,
     *   successful_targets: array<int, string>,
     *   failed_targets: array<int, string>,
     *   failed_results: array<int, array<string, mixed>>,
     *   observed_levels: array<int, int>,
     *   status_breakdown: array<string, int>
     * }
     */
    public static function auditSnapshot(array $audit, array $unresolvedTargets = []): array
    {
        $results = array_values(array_filter(
            is_array($audit['results'] ?? null) ? $audit['results'] : [],
            static fn (mixed $result): bool => is_array($result),
        ));

        $foundResults = array_values(array_filter(
            $results,
            static fn (array $result): bool => ($result['normalized_status'] ?? null) === 'found'
        ));

        $successfulTargets = array_values(array_unique(array_map(
            static fn (array $result): string => (string) ($result['target'] ?? ''),
            $foundResults
        )));

        $failedResults = array_values(array_filter(
            $results,
            static fn (array $result): bool => ($result['normalized_status'] ?? null) !== 'found'
        ));

        $failedTargets = array_values(array_unique(array_map(
            static fn (array $result): string => (string) ($result['target'] ?? ''),
            $failedResults
        )));
        $failedTargets = array_values(array_unique(array_merge(
            $failedTargets,
            array_values(array_map('strval', $unresolvedTargets)),
        )));

        $observedLevels = array_map(
            static fn (array $result): int => (int) ($result['observed_metadata_level'] ?? 0),
            $foundResults
        );

        $statusBreakdown = [];
        foreach ($results as $result) {
            $status = (string) ($result['normalized_status'] ?? 'unknown');
            $statusBreakdown[$status] = ($statusBreakdown[$status] ?? 0) + 1;
        }
        ksort($statusBreakdown);

        return [
            'results' => $results,
            'successful_targets' => $successfulTargets,
            'failed_targets' => $failedTargets,
            'failed_results' => $failedResults,
            'observed_levels' => $observedLevels,
            'status_breakdown' => $statusBreakdown,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $failedResults
     */
    public static function classifyFailureSet(array $failedResults): string
    {
        if (self::isInconclusiveFailureSet($failedResults)) {
            return 'inconclusive';
        }

        if (self::isBlockedFailureSet($failedResults)) {
            return 'blocked';
        }

        return 'broken';
    }

    /**
     * @param array<int, array<string, mixed>> $moduleReports
     * @return array<int, array<string, mixed>>
     */
    public static function sortModuleReports(array $moduleReports): array
    {
        usort($moduleReports, static fn (array $a, array $b): int => [$a['category'], $a['module']] <=> [$b['category'], $b['module']]);

        return $moduleReports;
    }

    /**
     * @param array<int, array<string, mixed>> $failedResults
     */
    private static function isInconclusiveFailureSet(array $failedResults): bool
    {
        if ($failedResults === []) {
            return false;
        }

        foreach ($failedResults as $result) {
            if (($result['normalized_status'] ?? null) !== 'error') {
                return false;
            }

            $statusDetail = (string) ($result['status_detail'] ?? '');
            if (!in_array($statusDetail, ['network_error', 'tls_blocked'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $failedResults
     */
    private static function isBlockedFailureSet(array $failedResults): bool
    {
        if ($failedResults === []) {
            return false;
        }

        foreach ($failedResults as $result) {
            if (($result['normalized_status'] ?? null) !== 'error') {
                return false;
            }

            $statusDetail = (string) ($result['status_detail'] ?? '');
            if (!in_array($statusDetail, ['anti_bot', 'blocked', 'rate_limited'], true)) {
                return false;
            }
        }

        return true;
    }
}
