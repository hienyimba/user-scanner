<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use App\DTO\ScanResult;

final class MetadataAuditService
{
    public function __construct(
        private readonly ScannerEngineService $engine,
        private readonly MetadataCapabilityService $metadataCapability,
        private readonly QueuedScanService $queuedScan,
    ) {
    }

    /**
     * @param array<int, string> $targets
     * @param array<int, string>|null $moduleKeys
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function audit(string $mode, array $targets, ?string $category = null, ?array $moduleKeys = null, array $options = []): array
    {
        $targets = array_values(array_filter(array_map(
            static fn (string $target): string => trim($target),
            array_unique($targets)
        ), static fn (string $target): bool => $target !== ''));
        $preparedOptions = $this->queuedScan->prepareOptions($options);

        $resultRecords = [];
        $planMeta = [];

        foreach ($targets as $target) {
            $scan = $this->engine->scanWithMeta($target, $mode, $category, $moduleKeys, $preparedOptions);
            $planMeta[] = $scan['meta'];

            foreach ($scan['results'] as $result) {
                $resultRecords[] = $this->resultRecord($mode, $target, $result);
            }
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'mode' => $mode,
            'targets' => $targets,
            'category' => $category,
            'module_keys' => $moduleKeys ?? [],
            'options' => $this->sanitizeOptions($preparedOptions),
            'plans' => $planMeta,
            'summary' => $this->summarize($resultRecords),
            'results' => $resultRecords,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resultRecord(string $mode, string $target, ScanResult $result): array
    {
        $payload = $result->toArray();
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $documented = $this->metadataCapability->forModule($mode, $result->key);
        $observedLevel = (int) ($metadata['observed_metadata_level'] ?? 0);
        $documentedLevel = is_array($documented) ? (int) $documented['level'] : null;
        $evidence = is_array($metadata['evidence'] ?? null) ? array_values($metadata['evidence']) : [];
        $nonEmptyMetadataKeys = $this->nonEmptyMetadataKeys($metadata);

        return [
            'target' => $target,
            'module' => $result->key,
            'platform' => $payload['platform'] ?? $result->key,
            'mode' => $mode,
            'category' => $payload['category'] ?? strtolower($result->category),
            'site_name' => $payload['site_name'] ?? $result->siteName,
            'status' => $payload['status'] ?? $result->status,
            'normalized_status' => $payload['normalized_status'] ?? $result->normalizedStatus,
            'status_detail' => $metadata['status_detail'] ?? null,
            'documented_capability_level' => $documentedLevel,
            'documented_capability_strategy' => $documented['strategy'] ?? 'unknown',
            'validated_capability_level' => is_array($documented) ? $documented['validated_level'] : null,
            'validated_capability_at' => is_array($documented) ? $documented['validated_at'] : null,
            'validated_capability_targets' => is_array($documented) ? $documented['validated_targets'] : [],
            'validated_capability_notes' => is_array($documented) ? $documented['validated_notes'] : null,
            'observed_metadata_level' => $observedLevel,
            'level_gap' => $documentedLevel === null ? null : $documentedLevel - $observedLevel,
            'confidence' => $payload['confidence'] ?? null,
            'profile_url' => $payload['profile_url'] ?? null,
            'evidence_count' => count($evidence),
            'evidence' => $evidence,
            'metadata_keys' => $nonEmptyMetadataKeys,
            'metadata' => $metadata,
            'normalized' => $payload['normalized'] ?? [],
            'error' => $payload['error'] ?? null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<string, mixed>
     */
    private function summarize(array $records): array
    {
        $summary = [
            'audited_results' => count($records),
            'found_results' => 0,
            'not_found_results' => 0,
            'error_results' => 0,
            'skipped_results' => 0,
            'profile_url_results' => 0,
            'documented_modules_seen' => 0,
            'found_results_below_documented_level' => 0,
            'results_with_evidence' => 0,
            'results_with_metadata_level_3_plus' => 0,
            'results_with_metadata_level_4' => 0,
            'status_detail_counts' => [],
            'observed_level_counts' => [
                'level_0' => 0,
                'level_1' => 0,
                'level_2' => 0,
                'level_3' => 0,
                'level_4' => 0,
            ],
        ];

        $documentedModules = [];

        foreach ($records as $record) {
            $normalizedStatus = (string) ($record['normalized_status'] ?? '');
            $observedLevel = (int) ($record['observed_metadata_level'] ?? 0);
            $statusDetail = (string) ($record['status_detail'] ?? 'unknown');
            $documentedLevel = $record['documented_capability_level'];

            match ($normalizedStatus) {
                'found' => $summary['found_results']++,
                'not_found' => $summary['not_found_results']++,
                'error' => $summary['error_results']++,
                'skipped' => $summary['skipped_results']++,
                default => null,
            };

            $summary['status_detail_counts'][$statusDetail] = ($summary['status_detail_counts'][$statusDetail] ?? 0) + 1;
            $summary['observed_level_counts']['level_' . $observedLevel] = ($summary['observed_level_counts']['level_' . $observedLevel] ?? 0) + 1;

            if (($record['profile_url'] ?? null) !== null) {
                $summary['profile_url_results']++;
            }
            if (($record['evidence_count'] ?? 0) > 0) {
                $summary['results_with_evidence']++;
            }
            if ($observedLevel >= 3) {
                $summary['results_with_metadata_level_3_plus']++;
            }
            if ($observedLevel >= 4) {
                $summary['results_with_metadata_level_4']++;
            }
            if ($documentedLevel !== null) {
                $documentedModules[(string) $record['mode'] . ':' . (string) $record['module']] = true;
                if ($normalizedStatus === 'found' && $observedLevel < (int) $documentedLevel) {
                    $summary['found_results_below_documented_level']++;
                }
            }
        }

        $summary['documented_modules_seen'] = count($documentedModules);
        ksort($summary['status_detail_counts']);

        return $summary;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<int, string>
     */
    private function nonEmptyMetadataKeys(array $metadata): array
    {
        $keys = [];
        foreach ($metadata as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $keys[] = (string) $key;
        }

        sort($keys);

        return $keys;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function sanitizeOptions(array $options): array
    {
        return [
            'allow_loud' => (bool) ($options['allow_loud'] ?? false),
            'no_nsfw' => (bool) ($options['no_nsfw'] ?? false),
            'only_found' => (bool) ($options['only_found'] ?? false),
            'disable_proxy' => (bool) ($options['disable_proxy'] ?? false),
            'use_proxy' => (bool) ($options['use_proxy'] ?? false),
            'proxy_supplied' => is_string($options['proxy'] ?? null) && trim((string) $options['proxy']) !== '',
            'enrich_metadata' => array_key_exists('enrich_metadata', $options) ? (bool) $options['enrich_metadata'] : (bool) config('scanner.metadata.fetch_profile_html', true),
            'stop' => (int) ($options['stop'] ?? 100),
            'delay' => (float) ($options['delay'] ?? 0),
        ];
    }
}
