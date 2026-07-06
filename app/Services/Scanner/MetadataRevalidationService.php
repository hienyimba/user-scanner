<?php

declare(strict_types=1);

namespace App\Services\Scanner;

final class MetadataRevalidationService
{
    public function __construct(
        private readonly MetadataAuditService $metadataAudit,
        private readonly MetadataCapabilityService $metadataCapability,
        private readonly MetadataTargetResolver $targetResolver,
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

            $resolvedTargets = $this->targetResolver->resolveMany($mode, $targets);
            $audit = $resolvedTargets['resolved'] === []
                ? MetadataBaselineValidationService::emptyAudit($mode)
                : $this->metadataAudit->audit($mode, $resolvedTargets['resolved'], null, [$capability['platform']], $options);
            $audit = MetadataBaselineValidationService::aliasAuditTargets($audit, $resolvedTargets['labels_by_resolved'] ?? []);
            $moduleReports[] = $this->summarizeModuleAudit($capability, $audit, $resolvedTargets['unresolved']);
        }

        $moduleReports = MetadataBaselineValidationService::sortModuleReports($moduleReports);

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
    private function summarizeModuleAudit(array $capability, array $audit, array $unresolvedTargets = []): array
    {
        $snapshot = MetadataBaselineValidationService::auditSnapshot($audit, $unresolvedTargets);
        $revalidatedLevel = $snapshot['observed_levels'] === [] ? null : min($snapshot['observed_levels']);
        $currentValidatedLevel = is_numeric($capability['validated_level'] ?? null)
            ? (int) $capability['validated_level']
            : null;

        return [
            'module' => (string) $capability['platform'],
            'category' => (string) ($capability['category'] ?? ''),
            'documented_capability_level' => $capability['level'] ?? null,
            'current_validated_level' => $currentValidatedLevel,
            'current_validated_at' => $capability['validated_at'] ?? null,
            'validated_targets' => array_values(array_map('strval', $capability['validated_targets'] ?? [])),
            'revalidated_level' => $revalidatedLevel,
            'revalidation_status' => $this->determineRevalidationStatus($currentValidatedLevel, $revalidatedLevel, $snapshot['successful_targets'], $snapshot['failed_targets'], $snapshot['failed_results']),
            'successful_targets' => $snapshot['successful_targets'],
            'failed_targets' => $snapshot['failed_targets'],
            'status_breakdown' => $snapshot['status_breakdown'],
            'audit_summary' => $audit['summary'] ?? [],
            'results' => $snapshot['results'],
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
            return MetadataBaselineValidationService::classifyFailureSet($failedResults);
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
