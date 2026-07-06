<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use App\Support\PublicMetadataNormalizer;
use Illuminate\Support\Facades\Http;

final class MetadataEnrichmentService
{
    public function __construct(
        private readonly ProfileMetadataExtractor $profileMetadataExtractor,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function enrich(ScanResult $result, ValidatorContract $validator, array $options = []): ScanResult
    {
        $platform = $validator->key();
        $normalizedStatus = $this->normalizeStatus($result->status);
        $profileUrl = $this->resolveProfileUrl($result, $validator);
        $existingObservedLevel = is_int($result->metadata['observed_metadata_level'] ?? null)
            ? (int) $result->metadata['observed_metadata_level']
            : null;
        $metadata = $this->mergeMetadata(
            $this->baseMetadata($result, $platform),
            $this->seedMetadata($result->metadata)
        );

        $legacyPairs = $this->parseLegacyExtra($result->extra);
        $metadata = $this->mergeLegacyMetadata($metadata, $legacyPairs);
        if ($legacyPairs !== []) {
            $metadata['sources'] = $this->mergeStringList($metadata['sources'] ?? [], ['legacy_extra']);
        }

        if ($normalizedStatus === 'found' && $result->mode === 'username' && $profileUrl !== null && $this->shouldFetchProfileHtml($options)) {
            $fetchedMetadata = $this->fetchProfileMetadata($profileUrl, $options);
            $metadata = $this->mergeMetadata($metadata, $fetchedMetadata);
        }

        $metadata = PublicMetadataNormalizer::normalize($metadata);
        $externalLinks = PublicMetadataNormalizer::normalizePublicLinks($metadata['external_links'] ?? []);
        $metadata['external_links'] = $externalLinks;
        $metadata['status_detail'] = is_string($metadata['status_detail'] ?? null) && trim((string) $metadata['status_detail']) !== ''
            ? trim((string) $metadata['status_detail'])
            : $this->determineStatusDetail($normalizedStatus, $result->reason);
        $metadata['evidence'] = $this->buildEvidence($result, $profileUrl, $metadata);
        $derivedObservedLevel = $this->determineObservedMetadataLevel($normalizedStatus, $profileUrl, $metadata);
        $metadata['observed_metadata_level'] = $existingObservedLevel !== null
            ? max($existingObservedLevel, $derivedObservedLevel)
            : $derivedObservedLevel;

        return new ScanResult(
            target: $result->target,
            category: $result->category,
            siteName: $result->siteName,
            url: $result->url,
            status: $result->status,
            reason: $result->reason,
            extra: $result->extra,
            mode: $result->mode,
            key: $result->key,
            platform: $platform,
            normalizedStatus: $normalizedStatus,
            profileUrl: $profileUrl,
            confidence: $result->confidence ?? $this->calculateConfidence($normalizedStatus, $profileUrl, $metadata),
            metadata: $metadata,
            externalLinks: $externalLinks,
            error: $normalizedStatus === 'error' ? ($result->error ?? $result->reason) : null,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function seedMetadata(array $metadata): array
    {
        if ($metadata === []) {
            return [];
        }

        unset(
            $metadata['observed_metadata_level'],
            $metadata['platform'],
        );

        if (isset($metadata['external_links'])) {
            $metadata['external_links'] = $this->normalizeExternalLinks($metadata['external_links']);
        }
        if (isset($metadata['sources'])) {
            $metadata['sources'] = $this->mergeStringList([], is_array($metadata['sources']) ? $metadata['sources'] : []);
        }
        if (isset($metadata['evidence'])) {
            $metadata['evidence'] = $this->mergeStringList([], is_array($metadata['evidence']) ? $metadata['evidence'] : []);
        }

        return $metadata;
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'found', 'registered', 'taken' => 'found',
            'not found', 'not registered', 'available' => 'not_found',
            'skipped' => 'skipped',
            'error' => 'error',
            default => strtolower(trim($status)),
        };
    }

    private function resolveProfileUrl(ScanResult $result, ValidatorContract $validator): ?string
    {
        $status = $this->normalizeStatus($result->status);
        if ($status !== 'found') {
            return null;
        }

        $validatorProfileUrl = method_exists($validator, 'publicProfileUrl')
            ? $validator->publicProfileUrl($result->target)
            : null;

        $explicitProfileUrl = $this->sanitizePublicProfileUrl($result->profileUrl, false, $result->target);
        if ($explicitProfileUrl !== null) {
            return $explicitProfileUrl;
        }

        foreach ([
            [$validatorProfileUrl, false],
            [$result->url, true],
            [$validator->siteUrl(), true],
        ] as [$candidate, $requireTargetEvidence]) {
            $resolved = $this->resolveTemplateCandidate($candidate, $result->target);
            if ($resolved === null) {
                continue;
            }

            $publicProfileUrl = $this->sanitizePublicProfileUrl($resolved, $requireTargetEvidence, $result->target);
            if ($publicProfileUrl !== null) {
                return $publicProfileUrl;
            }
        }

        return null;
    }

    private function resolveTemplateCandidate(mixed $candidate, string $target): ?string
    {
        if (!is_string($candidate) || trim($candidate) === '') {
            return null;
        }

        return str_replace(
            ['{user}', '{username}', '{target}'],
            rawurlencode($target),
            trim($candidate)
        );
    }

    private function sanitizePublicProfileUrl(?string $candidate, bool $requireTargetEvidence, string $target): ?string
    {
        if ($candidate === null) {
            return null;
        }

        $candidate = trim($candidate);
        if ($candidate === '' || preg_match('#^https?://#i', $candidate) !== 1) {
            return null;
        }

        $parsed = parse_url($candidate);
        if (!is_array($parsed)) {
            return null;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        $host = trim((string) ($parsed['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $hostLower = strtolower($host);
        $path = strtolower((string) ($parsed['path'] ?? ''));
        $query = strtolower((string) ($parsed['query'] ?? ''));

        if ($requireTargetEvidence) {
            $targetLower = strtolower($target);
            if ($targetLower === '' || (!str_contains($hostLower, $targetLower) && !str_contains($path, $targetLower) && !str_contains($query, $targetLower))) {
                return null;
            }
        }

        foreach ($this->blockedProfileUrlNeedles($requireTargetEvidence) as $needle) {
            if (str_contains($path, $needle) || str_contains($query, trim($needle, '/'))) {
                return null;
            }
        }

        $url = $scheme . '://' . $host;
        if (isset($parsed['port']) && is_int($parsed['port'])) {
            $url .= ':' . $parsed['port'];
        }
        $url .= (string) ($parsed['path'] ?? '');
        if (($parsed['query'] ?? '') !== '') {
            $url .= '?' . $parsed['query'];
        }

        return $url;
    }

    /**
     * @return array<int, string>
     */
    private function blockedProfileUrlNeedles(bool $strict): array
    {
        $needles = [
            '/api/',
            '/graphql',
            '/ajax/',
            '/signup',
            '/register',
            '/verify',
            '/check',
            '/lookup',
            '/w/api.php',
            '/exists',
        ];

        if ($strict) {
            $needles[] = '/search';
        }

        return $needles;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseMetadata(ScanResult $result, string $platform): array
    {
        return [
            'display_name' => null,
            'username' => $result->mode === 'username' ? $result->target : null,
            'avatar_url' => null,
            'bio' => null,
            'location' => null,
            'website_url' => null,
            'public_email' => null,
            'followers' => null,
            'following' => null,
            'posts_count' => null,
            'created_at' => null,
            'last_active_at' => null,
            'account_type' => null,
            'is_verified' => null,
            'is_private' => null,
            'external_links' => [],
            'sources' => [],
            'evidence' => [],
            'status_detail' => null,
            'observed_metadata_level' => 1,
            'platform' => $platform,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseLegacyExtra(string $extra): array
    {
        $pairs = [];
        foreach (preg_split('/\R/', $extra) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }

            [$label, $value] = array_map('trim', explode(':', $line, 2));
            if ($label === '' || $value === '') {
                continue;
            }

            $pairs[strtolower($label)] = $value;
        }

        return $pairs;
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, string> $legacyPairs
     * @return array<string, mixed>
     */
    private function mergeLegacyMetadata(array $metadata, array $legacyPairs): array
    {
        foreach ($legacyPairs as $label => $value) {
            $normalizedKey = str_replace([' ', '-'], '_', strtolower($label));

            match ($normalizedKey) {
                'name', 'full_name', 'real_name', 'display_name' => $metadata['display_name'] ??= $value,
                'username', 'login_name', 'handle' => $metadata['username'] ??= $value,
                'avatar', 'avatar_url', 'pfp' => $metadata['avatar_url'] ??= $value,
                'bio' => $metadata['bio'] ??= $value,
                'location' => $metadata['location'] ??= $value,
                'website', 'blog', 'website_url' => $metadata['website_url'] ??= $value,
                'email', 'public_email' => $metadata['public_email'] ??= $value,
                'followers' => $metadata['followers'] ??= $this->extractMetricValue($value),
                'following' => $metadata['following'] ??= $this->extractMetricValue($value),
                'stats' => $metadata = $this->mergeStatsString($metadata, $value),
                'public_repos', 'posts', 'posts_count', 'favorites' => $metadata['posts_count'] ??= $this->extractMetricValue($value),
                'joined', 'registration', 'created_at', 'created' => $metadata['created_at'] ??= $this->normalizeDateValue($value),
                'last_profile_update', 'updated_at', 'last_active_at' => $metadata['last_active_at'] ??= $this->normalizeDateValue($value),
                'account_type', 'account_types' => $metadata['account_type'] ??= $value,
                'verified', 'is_verified' => $metadata['is_verified'] ??= $this->parseBooleanish($value),
                'privacy', 'private', 'is_private' => $metadata['is_private'] ??= $this->parsePrivacyValue($value),
                'links', 'social_links', 'external_links' => $metadata['external_links'] = $this->mergeLinks($metadata['external_links'] ?? [], $value),
                default => $metadata[$normalizedKey] ??= $value,
            };
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function mergeStatsString(array $metadata, string $value): array
    {
        if (preg_match('/(\d+(?:\.\d+)?[kmb]?)\s+followers/i', $value, $followers)) {
            $metadata['followers'] ??= $this->extractMetricValue($followers[1]);
        }
        if (preg_match('/(\d+(?:\.\d+)?[kmb]?)\s+following/i', $value, $following)) {
            $metadata['following'] ??= $this->extractMetricValue($following[1]);
        }
        if (preg_match('/(\d+(?:\.\d+)?[kmb]?)\s+(favorites|posts|repos)/i', $value, $posts)) {
            $metadata['posts_count'] ??= $this->extractMetricValue($posts[1]);
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $newMetadata
     * @return array<string, mixed>
     */
    private function mergeMetadata(array $metadata, array $newMetadata): array
    {
        foreach ($newMetadata as $key => $value) {
            if (in_array($key, ['blocked_metadata_fields', 'sensitive_fields'], true)) {
                $metadata[$key] = $this->mergeStringList([], is_array($value) ? $value : []);
                continue;
            }

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if ($key === 'external_links') {
                $metadata[$key] = $this->mergeLinks($metadata[$key] ?? [], $value);
                continue;
            }

            if (in_array($key, ['sources', 'evidence'], true)) {
                $metadata[$key] = $this->mergeStringList($metadata[$key] ?? [], $value);
                continue;
            }

            if (($metadata[$key] ?? null) === null || ($metadata[$key] ?? '') === '') {
                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function fetchProfileMetadata(string $profileUrl, array $options): array
    {
        try {
            $request = Http::timeout((int) config('scanner.metadata.request_timeout_seconds', 8))
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->withHeaders([
                    'User-Agent' => (string) config('scanner.user_agent'),
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ]);

            if (!empty($options['proxy']) && is_string($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->get($profileUrl);
            if ($response->status() !== 200) {
                return [];
            }

            return $this->profileMetadataExtractor->extractProfileHtmlMetadata(
                substr($response->body(), 0, (int) config('scanner.metadata.max_html_bytes', 262144)),
                $profileUrl,
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private function shouldFetchProfileHtml(array $options): bool
    {
        if (array_key_exists('enrich_metadata', $options)) {
            return (bool) $options['enrich_metadata'];
        }

        return (bool) config('scanner.metadata.fetch_profile_html', true);
    }

    /**
     * @param array<int, string>|string $value
     * @return array<int, string>
     */
    private function mergeLinks(array|string $existing, array|string $value): array
    {
        return $this->profileMetadataExtractor->mergeLinks($existing, $value);
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalizeExternalLinks(mixed $value): array
    {
        return $this->profileMetadataExtractor->normalizeExternalLinks($value);
    }

    /**
     * @return int|float|string
     */
    private function extractMetricValue(string $value): int|float|string
    {
        return $this->profileMetadataExtractor->extractMetricValue($value);
    }

    private function normalizeDateValue(string $value): string
    {
        return $this->profileMetadataExtractor->normalizeDateValue($value);
    }

    private function parseBooleanish(string $value): ?bool
    {
        return match (strtolower(trim($value))) {
            'yes', 'true', 'public', 'verified' => true,
            'no', 'false', 'private', 'unverified' => false,
            default => null,
        };
    }

    private function parsePrivacyValue(string $value): ?bool
    {
        $lower = strtolower(trim($value));
        if (str_contains($lower, 'private')) {
            return true;
        }
        if (str_contains($lower, 'public')) {
            return false;
        }

        return $this->parseBooleanish($value);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function calculateConfidence(string $normalizedStatus, ?string $profileUrl, array $metadata): ?float
    {
        return match ($normalizedStatus) {
            'error', 'skipped' => 0.0,
            'not_found' => 0.95,
            'found' => $this->foundConfidence($profileUrl, $metadata),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function foundConfidence(?string $profileUrl, array $metadata): float
    {
        $confidence = $profileUrl !== null ? 0.82 : 0.74;

        foreach (['display_name', 'avatar_url', 'bio', 'website_url', 'public_email', 'followers'] as $signal) {
            if (($metadata[$signal] ?? null) !== null && ($metadata[$signal] ?? '') !== '') {
                $confidence += 0.03;
            }
        }

        if (($metadata['external_links'] ?? []) !== []) {
            $confidence += 0.02;
        }

        return min(0.99, round($confidence, 2));
    }

    private function determineStatusDetail(string $normalizedStatus, string $reason): string
    {
        if ($normalizedStatus !== 'error') {
            return $normalizedStatus;
        }

        $message = strtolower(trim($reason));

        return match (true) {
            str_contains($message, 'anti-bot challenge'),
            str_contains($message, 'captcha'),
            str_contains($message, 'waf'),
            str_contains($message, 'security verification') => 'anti_bot',
            str_contains($message, 'rate-limited'),
            str_contains($message, 'http 429') => 'rate_limited',
            str_contains($message, 'http 403'),
            str_contains($message, 'blocked') => 'blocked',
            str_contains($message, 'timeout') => 'timeout',
            str_contains($message, 'tls/anti-bot'),
            str_contains($message, 'unexpected eof while reading') => 'tls_blocked',
            str_contains($message, 'failed to connect'),
            str_contains($message, 'could not resolve host'),
            str_contains($message, 'connection refused'),
            str_contains($message, 'bad access') => 'network_error',
            str_contains($message, 'invalid api response format'),
            str_contains($message, 'unexpected response'),
            str_contains($message, 'xml-rpc'),
            str_contains($message, 'unexpected signature') => 'parse_error',
            default => 'error',
        };
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<int, string>
     */
    private function buildEvidence(ScanResult $result, ?string $profileUrl, array $metadata): array
    {
        $evidence = [];
        if ($profileUrl !== null) {
            $evidence[] = 'profile_url';
        }
        if (trim($result->extra) !== '') {
            $evidence[] = 'legacy_extra';
        }

        foreach (['display_name', 'avatar_url', 'bio', 'website_url', 'public_email', 'followers', 'following', 'posts_count', 'created_at', 'last_active_at'] as $field) {
            $value = $metadata[$field] ?? null;
            if ($value !== null && $value !== '' && $value !== []) {
                $evidence[] = $field;
            }
        }

        foreach ($metadata as $key => $value) {
            if (in_array($key, [
                'display_name',
                'username',
                'avatar_url',
                'bio',
                'location',
                'website_url',
                'public_email',
                'followers',
                'following',
                'posts_count',
                'created_at',
                'last_active_at',
                'account_type',
                'is_verified',
                'is_private',
                'external_links',
                'sources',
                'evidence',
                'status_detail',
                'observed_metadata_level',
                'platform',
                'http_status',
                'latency_ms',
                'proxy_used',
            ], true)) {
                continue;
            }

            if ($value !== null && $value !== '' && $value !== []) {
                $evidence[] = $key;
            }
        }

        if (($metadata['external_links'] ?? []) !== []) {
            $evidence[] = 'external_links';
        }

        if (($metadata['sources'] ?? []) !== []) {
            $evidence = [...$evidence, ...$this->mergeStringList([], $metadata['sources'])];
        }

        return array_values(array_unique($evidence));
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function determineObservedMetadataLevel(string $normalizedStatus, ?string $profileUrl, array $metadata): int
    {
        if (in_array($normalizedStatus, ['error', 'skipped'], true)) {
            return 0;
        }

        if ($normalizedStatus === 'not_found') {
            return 1;
        }

        $signalCount = 0;
        foreach (['display_name', 'avatar_url', 'bio', 'location', 'website_url', 'public_email', 'followers', 'following', 'posts_count', 'created_at', 'last_active_at', 'account_type'] as $field) {
            $value = $metadata[$field] ?? null;
            if ($value !== null && $value !== '') {
                $signalCount++;
            }
        }

        $customSignals = 0;
        foreach ($metadata as $key => $value) {
            if (in_array($key, [
                'display_name',
                'username',
                'avatar_url',
                'bio',
                'location',
                'website_url',
                'public_email',
                'followers',
                'following',
                'posts_count',
                'created_at',
                'last_active_at',
                'account_type',
                'is_verified',
                'is_private',
                'external_links',
                'sources',
                'evidence',
                'status_detail',
                'observed_metadata_level',
                'platform',
                'http_status',
                'latency_ms',
                'proxy_used',
            ], true)) {
                continue;
            }

            if ($value !== null && $value !== '' && $value !== []) {
                $customSignals++;
            }
        }

        $hasLinks = ($metadata['external_links'] ?? []) !== [];
        $evidenceCount = count($metadata['evidence'] ?? []);
        $totalSignals = $signalCount + $customSignals;

        if ($totalSignals >= 3 && ($profileUrl !== null || $hasLinks || $customSignals >= 1) && $evidenceCount >= 4) {
            return 4;
        }

        if ($totalSignals >= 1 || $hasLinks) {
            return 3;
        }

        return $profileUrl !== null ? 2 : 1;
    }

    /**
     * @param array<int, string>|string $value
     * @return array<int, string>
     */
    private function mergeStringList(array|string $existing, array|string $value): array
    {
        return $this->profileMetadataExtractor->mergeStringList($existing, $value);
    }
}
