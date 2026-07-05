<?php

declare(strict_types=1);

namespace App\Services\Scanner;

final class MetadataRevalidationService
{
    public function __construct(
        private readonly MetadataAuditService $metadataAudit,
        private readonly MetadataCapabilityService $metadataCapability,
    ) {
    }

    /**
     * @param array<int, string>|null $moduleKeys
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function revalidate(string $mode, ?array $moduleKeys = null, array $options = []): array
    {
        $modules = array_values(array_filter(
            $this->metadataCapability->all(),
            static fn (array $record): bool => $record['mode'] === $mode
                && $record['validated_level'] !== null
                && ($record['validated_targets'] ?? []) !== []
        ));

        if ($moduleKeys !== null && $moduleKeys !== []) {
            $filter = array_fill_keys($moduleKeys, true);
            $modules = array_values(array_filter(
                $modules,
                static fn (array $record): bool => isset($filter[$record['platform']])
            ));
        }

        $moduleReports = [];
        foreach ($modules as $capability) {
            $targets = array_values(array_map('strval', $capability['validated_targets'] ?? []));
            if ($targets === []) {
                continue;
            }

            $audit = $this->metadataAudit->audit($mode, $targets, null, [$capability['platform']], $options);
            $moduleReports[] = $this->summarizeModuleAudit($capability, $audit);
        }

        usort($moduleReports, static fn (array $a, array $b): int => [$a['category'], $a['module']] <=> [$b['category'], $b['module']]);

        return [
            'generated_at' => now()->toIso8601String(),
            'mode' => $mode,
            'requested_modules' => array_values(array_map(
                static fn (array $record): string => (string) $record['platform'],
                $modules
            )),
            'summary' => $this->overallSummary($moduleReports),
            'modules' => $moduleReports,
        ];
    }

    /**
     * @param array<string, mixed> $capability
     * @param array<string, mixed> $audit
     * @return array<string, mixed>
     */
    private function summarizeModuleAudit(array $capability, array $audit): array
    {
        $results = is_array($audit['results'] ?? null) ? $audit['results'] : [];
        $foundResults = array_values(array_filter(
            $results,
            static fn (array $result): bool => ($result['normalized_status'] ?? null) === 'found'
        ));
        $successfulTargets = array_values(array_unique(array_map(
            static fn (array $result): string => (string) $result['target'],
            $foundResults
        )));
        $failedTargets = array_values(array_unique(array_map(
            static fn (array $result): string => (string) $result['target'],
            array_filter(
                $results,
                static fn (array $result): bool => ($result['normalized_status'] ?? null) !== 'found'
            )
        )));
        $failedResults = array_values(array_filter(
            $results,
            static fn (array $result): bool => ($result['normalized_status'] ?? null) !== 'found'
        ));

        $observedLevels = array_map(
            static fn (array $result): int => (int) ($result['observed_metadata_level'] ?? 0),
            $foundResults
        );
        $revalidatedLevel = $observedLevels === [] ? null : min($observedLevels);
        $currentValidatedLevel = is_numeric($capability['validated_level'] ?? null)
            ? (int) $capability['validated_level']
            : null;

        $statusBreakdown = [];
        foreach ($results as $result) {
            $status = (string) ($result['normalized_status'] ?? 'unknown');
            $statusBreakdown[$status] = ($statusBreakdown[$status] ?? 0) + 1;
        }
        ksort($statusBreakdown);

        return [
            'module' => (string) $capability['platform'],
            'category' => (string) ($capability['category'] ?? ''),
            'documented_capability_level' => $capability['level'] ?? null,
            'current_validated_level' => $currentValidatedLevel,
            'current_validated_at' => $capability['validated_at'] ?? null,
            'validated_targets' => array_values(array_map('strval', $capability['validated_targets'] ?? [])),
            'revalidated_level' => $revalidatedLevel,
            'revalidation_status' => $this->determineRevalidationStatus($currentValidatedLevel, $revalidatedLevel, $successfulTargets, $failedTargets, $failedResults),
            'successful_targets' => $successfulTargets,
            'failed_targets' => $failedTargets,
            'status_breakdown' => $statusBreakdown,
            'audit_summary' => $audit['summary'] ?? [],
            'results' => $results,
        ];
    }

    /**
     * @param int|null $currentValidatedLevel
     * @param int|null $revalidatedLevel
     * @param array<int, string> $successfulTargets
     * @param array<int, string> $failedTargets
     * @param array<int, array<string, mixed>> $failedResults
     */
    private function determineRevalidationStatus(
        ?int $currentValidatedLevel,
        ?int $revalidatedLevel,
        array $successfulTargets,
        array $failedTargets,
        array $failedResults,
    ): string {
        if ($successfulTargets === []) {
            if ($this->isInconclusiveFailureSet($failedResults)) {
                return 'inconclusive';
            }
            if ($this->isBlockedFailureSet($failedResults)) {
                return 'blocked';
            }

            return 'broken';
        }

        if ($currentValidatedLevel !== null && $revalidatedLevel !== null && $revalidatedLevel < $currentValidatedLevel) {
            return 'degraded';
        }

        if ($failedTargets !== []) {
            return 'partial';
        }

        return 'stable';
    }

    /**
     * @param array<int, array<string, mixed>> $failedResults
     */
    private function isInconclusiveFailureSet(array $failedResults): bool
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
    private function isBlockedFailureSet(array $failedResults): bool
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

    /**
     * @param array<int, array<string, mixed>> $moduleReports
     * @return array<string, int>
     */
    private function overallSummary(array $moduleReports): array
    {
        $summary = [
            'modules_requested' => count($moduleReports),
            'stable_modules' => 0,
            'partial_modules' => 0,
            'degraded_modules' => 0,
            'blocked_modules' => 0,
            'broken_modules' => 0,
            'inconclusive_modules' => 0,
            'unstable_modules' => 0,
            'successful_targets' => 0,
            'failed_targets' => 0,
        ];

        foreach ($moduleReports as $report) {
            $status = (string) ($report['revalidation_status'] ?? 'stable');
            if (isset($summary[$status . '_modules'])) {
                $summary[$status . '_modules']++;
            }

            if ($status !== 'stable') {
                $summary['unstable_modules']++;
            }

            $summary['successful_targets'] += count($report['successful_targets'] ?? []);
            $summary['failed_targets'] += count($report['failed_targets'] ?? []);
        }

        return $summary;
    }
}
