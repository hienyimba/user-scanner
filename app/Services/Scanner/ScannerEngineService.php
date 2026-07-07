<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use Illuminate\Support\Arr;

final class ScannerEngineService
{
    /** @param iterable<ValidatorContract> $validators */
    public function __construct(
        private readonly iterable $validators,
        private readonly ProxyManagerService $proxyManager,
        private readonly PatternExpanderService $patternExpander,
        private readonly MetadataEnrichmentService $metadataEnrichment,
        private readonly MetadataCapabilityService $metadataCapability,
        private readonly ModuleSkipService $moduleSkips,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     * @return array{results: array<int, ScanResult>, meta: array<string, mixed>}
     */
    public function scanWithMeta(string $target, string $mode, ?string $category = null, ?array $moduleKeys = null, array $options = []): array
    {
        $plan = $this->planScan($target, $mode, $category, $moduleKeys, $options);
        $expandedTargets = $plan['expanded_targets'];
        $totalPermutations = $plan['total_permutations'];
        $selected = $plan['validators'];

        $results = [];
        $attemptOffset = 0;
        foreach ($expandedTargets as $index => $expandedTarget) {
            if ($index !== 0 && (float) ($options['delay'] ?? 0) > 0) {
                usleep((int) (((float) $options['delay']) * 1_000_000));
            }

            foreach ($selected as $validator) {
                $result = $this->runValidator($validator, $expandedTarget, $mode, [
                    ...$options,
                    'proxy_offset' => $attemptOffset,
                ]);
                $attemptOffset++;
                if ((bool) ($options['only_found'] ?? false) && !in_array($result->status, ['Found', 'Registered'], true)) {
                    continue;
                }

                $results[] = $result;
            }
        }

        return [
            'results' => $results,
            'meta' => [
                'target' => $target,
                'mode' => $mode,
                'expanded_targets' => $expandedTargets,
                'total_permutations' => $totalPermutations,
                'modules_scanned' => count($selected),
                'expected_results' => $plan['expected_results'],
                'proxy_count' => $this->proxyManager->count(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, ScanResult>
     */
    public function scan(string $target, string $mode, ?string $category = null, ?array $moduleKeys = null, array $options = []): array
    {
        return $this->scanWithMeta($target, $mode, $category, $moduleKeys, $options)['results'];
    }

    /**
     * @return array<int, array{key:string,category:string,mode:string,site_name:string,url:string,loud:bool,nsfw:bool}>
     */
    public function listModules(string $mode, bool $noNsfw = false): array
    {
        $skipFlags = $this->moduleSkips->activeSkipsForMany($mode, $this->allValidatorKeysForMode($mode));
        $modules = [];
        $seen = [];
        foreach ($this->validators as $validator) {
            if ($validator->mode() !== $mode) {
                continue;
            }

            $registryKey = $validator->mode() . ':' . $validator->key();
            if (isset($seen[$registryKey])) {
                continue;
            }
            $seen[$registryKey] = true;

            $category = strtolower($validator->category());
            $isNsfw = in_array($category, config('scanner.nsfw_categories', ['adult']), true);
            if ($noNsfw && $isNsfw) {
                continue;
            }

            $modules[] = [
                'key' => $validator->key(),
                'category' => $category,
                'mode' => $validator->mode(),
                'site_name' => $validator->siteName(),
                'url' => $validator->siteUrl(),
                'loud' => $this->isLoud($validator->siteName(), $mode),
                'nsfw' => $isNsfw,
                'metadata_capability_level' => $this->metadataCapability->forModule($mode, $validator->key())['level'] ?? 1,
                'metadata_capability_strategy' => $this->metadataCapability->forModule($mode, $validator->key())['strategy'] ?? 'unknown',
                'metadata_capability_notes' => $this->metadataCapability->forModule($mode, $validator->key())['notes'] ?? 'No capability audit entry found',
                'metadata_validated_level' => $this->metadataCapability->forModule($mode, $validator->key())['validated_level'] ?? null,
                'metadata_validated_at' => $this->metadataCapability->forModule($mode, $validator->key())['validated_at'] ?? null,
                'metadata_validated_targets' => $this->metadataCapability->forModule($mode, $validator->key())['validated_targets'] ?? [],
                'metadata_validated_notes' => $this->metadataCapability->forModule($mode, $validator->key())['validated_notes'] ?? null,
                'skip_active' => isset($skipFlags[$validator->key()]),
                'skip_reason' => $skipFlags[$validator->key()]['reason'] ?? null,
                'skip_expires_at' => $skipFlags[$validator->key()]['expires_at'] ?? null,
                'skip_label' => $skipFlags[$validator->key()]['label'] ?? null,
                'skip_duration' => $skipFlags[$validator->key()]['duration'] ?? null,
            ];
        }

        usort($modules, static fn (array $a, array $b): int => [$a['category'], $a['site_name']] <=> [$b['category'], $b['site_name']]);

        return $modules;
    }

    /**
     * @return array<int, string>
     */
    public function listCategories(string $mode, bool $noNsfw = false): array
    {
        $categories = array_values(array_unique(array_map(
            static fn (array $module): string => $module['category'],
            $this->listModules($mode, $noNsfw)
        )));

        sort($categories);

        return $categories;
    }

    /**
     * @param array<string, mixed> $options
     * @return array{
     *   target:string,
     *   mode:string,
     *   expanded_targets: array<int, string>,
     *   total_permutations:int,
     *   validators: array<int, ValidatorContract>,
     *   validator_keys: array<int, string>,
     *   expected_results:int,
     *   modules_scanned:int,
     *   proxy_count:int
     * }
     */
    public function planScan(string $target, string $mode, ?string $category = null, ?array $moduleKeys = null, array $options = []): array
    {
        $expandedTargets = $this->patternExpander->expandRandomized($target, (int) ($options['stop'] ?? 100));
        $selected = $this->selectValidators($mode, $category, $moduleKeys, (bool) ($options['no_nsfw'] ?? false));

        return [
            'target' => $target,
            'mode' => $mode,
            'expanded_targets' => $expandedTargets,
            'total_permutations' => $this->patternExpander->count($target),
            'validators' => $selected,
            'validator_keys' => array_map(static fn (ValidatorContract $validator): string => $validator->key(), $selected),
            'expected_results' => count($expandedTargets) * count($selected),
            'modules_scanned' => count($selected),
            'proxy_count' => $this->proxyManager->count(),
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function runPlannedValidator(string $mode, string $validatorKey, string $target, array $options = []): ScanResult
    {
        $validator = $this->resolveValidator($mode, $validatorKey);
        if ($validator === null) {
            return ScanResult::fromArray([
                'target' => $target,
                'category' => '',
                'site_name' => $validatorKey,
                'url' => '',
                'status' => 'Error',
                'reason' => sprintf('Validator "%s" not found for mode %s', $validatorKey, $mode),
                'mode' => $mode,
                'key' => $validatorKey,
            ]);
        }

        return $this->runValidator($validator, $target, $mode, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function runValidator(ValidatorContract $validator, string $target, string $mode, array $options): ScanResult
    {
        $skipFlag = $this->moduleSkips->activeSkipFor($mode, $validator->key());
        if ($skipFlag !== null) {
            return $this->metadataEnrichment->enrich(new ScanResult(
                target: $target,
                category: strtolower($validator->category()),
                siteName: $validator->siteName(),
                url: $validator->siteUrl(),
                status: 'Skipped',
                reason: (string) ($skipFlag['reason'] ?? 'Skipped from ops dashboard'),
                mode: $mode,
                key: $validator->key(),
            ), $validator, $options);
        }

        $autoSkipReason = $this->autoSkipReason($validator->key(), $mode);
        if ($autoSkipReason !== null) {
            return $this->metadataEnrichment->enrich(new ScanResult(
                target: $target,
                category: strtolower($validator->category()),
                siteName: $validator->siteName(),
                url: $validator->siteUrl(),
                status: 'Skipped',
                reason: $autoSkipReason,
                mode: $mode,
                key: $validator->key(),
            ), $validator, $options);
        }

        if (!(bool) ($options['allow_loud'] ?? false) && $this->isLoud($validator->siteName(), $mode)) {
            return $this->metadataEnrichment->enrich(new ScanResult(
                target: $target,
                category: strtolower($validator->category()),
                siteName: $validator->siteName(),
                url: $validator->siteUrl(),
                status: 'Skipped',
                reason: 'Notifies the target by forgot password email or similar',
                mode: $mode,
                key: $validator->key(),
            ), $validator, $options);
        }

        if (array_key_exists('proxy', $options)) {
            $proxy = is_string($options['proxy']) && $options['proxy'] !== ''
                ? $this->proxyManager->resolve($options['proxy'])
                : null;

            return $this->executeValidator($validator, $target, $mode, $options, $proxy);
        }

        if ((bool) Arr::get($options, 'use_proxy', false)) {
            return $this->runValidatorWithProxyPolicy($validator, $target, $mode, $options);
        }

        return $this->executeValidator($validator, $target, $mode, $options, null);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function runValidatorWithProxyPolicy(ValidatorContract $validator, string $target, string $mode, array $options): ScanResult
    {
        return ProxyExecutionPolicy::run(
            proxyManager: $this->proxyManager,
            options: $options,
            offset: (int) Arr::get($options, 'proxy_offset', 0),
            attempt: fn (?string $proxy): ScanResult => $this->executeValidator($validator, $target, $mode, $options, $proxy),
            errorResult: fn (string $reason): ScanResult => ScanResult::fromArray([
                'target' => $target,
                'category' => strtolower($validator->category()),
                'site_name' => $validator->siteName(),
                'url' => $validator->siteUrl(),
                'status' => 'Error',
                'reason' => $reason,
                'mode' => $mode,
                'key' => $validator->key(),
            ]),
            fallbackWithoutProxyWhenPoolEmpty: true,
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function executeValidator(ValidatorContract $validator, string $target, string $mode, array $options, ?string $proxy): ScanResult
    {
        $rawResult = $validator->check($target, [
            ...$options,
            'proxy' => $proxy,
        ]);

        $normalized = ScanResult::fromArray([
            'target' => $rawResult->target,
            'category' => $rawResult->category,
            'site_name' => $rawResult->siteName,
            'url' => $rawResult->url,
            'status' => $this->normalizeStatus($mode, $rawResult->status),
            'reason' => $rawResult->reason,
            'extra' => $rawResult->extra,
            'mode' => $mode,
            'key' => $validator->key(),
            'platform' => $rawResult->platform,
            'normalized_status' => $rawResult->normalizedStatus,
            'profile_url' => $rawResult->profileUrl,
            'confidence' => $rawResult->confidence,
            'metadata' => $rawResult->metadata,
            'external_links' => $rawResult->externalLinks,
            'error' => $rawResult->error,
        ]);

        return $this->metadataEnrichment->enrich($normalized, $validator, [
            ...$options,
            'proxy' => $proxy,
        ]);
    }

    /**
     * @return array<int, ValidatorContract>
     */
    private function selectValidators(string $mode, ?string $category, ?array $moduleKeys, bool $noNsfw): array
    {
        $selected = [];
        $seen = [];
        $skipFlags = $this->moduleSkips->activeSkipsForMany($mode, $this->allValidatorKeysForMode($mode));

        foreach ($this->validators as $validator) {
            if ($validator->mode() !== $mode) {
                continue;
            }

            $registryKey = $validator->mode() . ':' . $validator->key();
            if (isset($seen[$registryKey])) {
                continue;
            }
            $seen[$registryKey] = true;

            if ($category !== null && strcasecmp($validator->category(), $category) !== 0) {
                continue;
            }

            if ($moduleKeys !== null && $moduleKeys !== [] && !in_array($validator->key(), $moduleKeys, true)) {
                continue;
            }

            if ($noNsfw && in_array(strtolower($validator->category()), config('scanner.nsfw_categories', ['adult']), true)) {
                continue;
            }

            if (isset($skipFlags[$validator->key()])) {
                continue;
            }

            $selected[] = $validator;
        }

        usort($selected, static fn (ValidatorContract $a, ValidatorContract $b): int => [$a->category(), $a->siteName()] <=> [$b->category(), $b->siteName()]);

        return $selected;
    }

    private function resolveValidator(string $mode, string $validatorKey): ?ValidatorContract
    {
        foreach ($this->validators as $validator) {
            if ($validator->mode() !== $mode) {
                continue;
            }

            if ($validator->key() === $validatorKey) {
                return $validator;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function allValidatorKeysForMode(string $mode): array
    {
        $keys = [];
        $seen = [];

        foreach ($this->validators as $validator) {
            if ($validator->mode() !== $mode) {
                continue;
            }

            if (isset($seen[$validator->key()])) {
                continue;
            }

            $seen[$validator->key()] = true;
            $keys[] = $validator->key();
        }

        return $keys;
    }

    private function isLoud(string $siteName, string $mode): bool
    {
        $catalog = config($mode === 'email' ? 'scanner.loud_modules.email' : 'scanner.loud_modules.username', []);

        return in_array(strtolower($siteName), $catalog, true);
    }

    private function autoSkipReason(string $validatorKey, string $mode): ?string
    {
        $catalog = config($mode === 'email' ? 'scanner.auto_skip.email' : 'scanner.auto_skip.username', []);

        $reason = $catalog[strtolower($validatorKey)] ?? null;

        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    private function normalizeStatus(string $mode, string $status): string
    {
        if ($mode === 'username') {
            return match ($status) {
                'Available' => 'Not Found',
                'Taken' => 'Found',
                default => $status,
            };
        }

        return $status;
    }
}
