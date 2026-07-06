<?php

declare(strict_types=1);

namespace App\DTO;

use App\Support\PublicMetadataNormalizer;

final class ScanResult
{
    /**
     * @param array<string, mixed> $metadata
     * @param array<int, string> $externalLinks
     */
    public function __construct(
        public readonly string $target,
        public readonly string $category,
        public readonly string $siteName,
        public readonly string $url,
        public readonly string $status,
        public readonly string $reason = '',
        public readonly string $extra = '',
        public readonly string $mode = 'username',
        public readonly string $key = '',
        public readonly string $platform = '',
        public readonly string $normalizedStatus = '',
        public readonly ?string $profileUrl = null,
        public readonly ?float $confidence = null,
        public readonly array $metadata = [],
        public readonly array $externalLinks = [],
        public readonly ?string $error = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $extra = $data['extra'] ?? '';
        $metadata = self::normalizeMetadataPayload($data['metadata'] ?? null);

        if ($metadata === [] && is_array($extra)) {
            $metadata = self::normalizeMetadataPayload($extra);
            $extra = self::summarizeMetadata($metadata);
        }

        if (!is_string($extra)) {
            $extra = (string) $extra;
        }

        $status = (string) ($data['status'] ?? '');
        $platform = (string) ($data['platform'] ?? ($data['key'] ?? ''));
        $normalizedStatus = (string) ($data['normalized_status'] ?? self::normalizeStatusLabel($status));
        $externalLinks = self::normalizeExternalLinks($data['external_links'] ?? ($metadata['external_links'] ?? []));
        if ($externalLinks !== [] && !isset($metadata['external_links'])) {
            $metadata['external_links'] = $externalLinks;
        }

        return new self(
            target: (string) ($data['target'] ?? ''),
            category: strtolower((string) ($data['category'] ?? '')),
            siteName: (string) ($data['site_name'] ?? ''),
            url: (string) ($data['url'] ?? ''),
            status: $status,
            reason: (string) ($data['reason'] ?? ''),
            extra: $extra,
            mode: (string) ($data['mode'] ?? 'username'),
            key: (string) ($data['key'] ?? ''),
            platform: $platform,
            normalizedStatus: $normalizedStatus,
            profileUrl: self::nullableString($data['profile_url'] ?? null),
            confidence: self::nullableFloat($data['confidence'] ?? null),
            metadata: $metadata,
            externalLinks: $externalLinks,
            error: self::nullableString($data['error'] ?? ($normalizedStatus === 'error' ? ($data['reason'] ?? null) : null)),
        );
    }

    public function toArray(): array
    {
        $platform = $this->platform !== '' ? $this->platform : $this->key;
        $normalizedStatus = $this->normalizedStatus !== '' ? $this->normalizedStatus : self::normalizeStatusLabel($this->status);
        $profileUrl = self::resolveProfileUrlForOutput($this->profileUrl, $this->url, $normalizedStatus, $this->mode, $this->target);
        $metadata = self::normalizeMetadataForOutput(
            metadata: $this->metadata,
            mode: $this->mode,
            target: $this->target,
            platform: $platform,
            normalizedStatus: $normalizedStatus,
            profileUrl: $profileUrl,
            reason: $this->reason,
            externalLinks: $this->externalLinks !== [] ? $this->externalLinks : ($this->metadata['external_links'] ?? []),
            extra: $this->extra,
        );
        $externalLinks = $metadata['external_links'];
        $confidence = $this->confidence ?? self::deriveConfidence($normalizedStatus, $profileUrl, $metadata);
        $error = $this->error ?? ($normalizedStatus === 'error' ? $this->reason : null);
        $normalized = $this->buildNormalizedArray(
            $platform,
            $normalizedStatus,
            $profileUrl,
            $confidence,
            $metadata,
            $error,
        );

        return [
            'target' => $this->target,
            'category' => strtolower($this->category),
            'site_name' => $this->siteName,
            'url' => $this->url,
            'status' => $this->status,
            'reason' => $this->reason,
            'extra' => $this->extra,
            'mode' => $this->mode,
            'key' => $this->key,
            'platform' => $platform,
            'normalized_status' => $normalizedStatus,
            'profile_url' => $profileUrl,
            'confidence' => $confidence,
            'metadata' => $metadata,
            'external_links' => $externalLinks,
            'error' => $error,
            'normalized' => $normalized,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toNormalizedArray(): array
    {
        $platform = $this->platform !== '' ? $this->platform : $this->key;
        $normalizedStatus = $this->normalizedStatus !== '' ? $this->normalizedStatus : self::normalizeStatusLabel($this->status);
        $profileUrl = self::resolveProfileUrlForOutput($this->profileUrl, $this->url, $normalizedStatus, $this->mode, $this->target);
        $metadata = self::normalizeMetadataForOutput(
            metadata: $this->metadata,
            mode: $this->mode,
            target: $this->target,
            platform: $platform,
            normalizedStatus: $normalizedStatus,
            profileUrl: $profileUrl,
            reason: $this->reason,
            externalLinks: $this->externalLinks !== [] ? $this->externalLinks : ($this->metadata['external_links'] ?? []),
            extra: $this->extra,
        );
        $externalLinks = $metadata['external_links'];
        $confidence = $this->confidence ?? self::deriveConfidence($normalizedStatus, $profileUrl, $metadata);
        $error = $this->error ?? ($normalizedStatus === 'error' ? $this->reason : null);

        return $this->buildNormalizedArray(
            $platform,
            $normalizedStatus,
            $profileUrl,
            $confidence,
            $metadata,
            $error,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function buildNormalizedArray(
        string $platform,
        string $normalizedStatus,
        ?string $profileUrl,
        ?float $confidence,
        array $metadata,
        ?string $error,
    ): array {
        return [
            'target' => $this->target,
            'category' => strtolower($this->category),
            'mode' => $this->mode,
            'platform' => $platform,
            'url' => $this->url,
            'status' => $normalizedStatus,
            'status_detail' => $metadata['status_detail'] ?? null,
            'confidence' => $confidence,
            'profile_url' => $profileUrl,
            'metadata_level' => $metadata['observed_metadata_level'] ?? null,
            'metadata' => $metadata,
            'evidence' => self::normalizeExternalLinks($metadata['evidence'] ?? []),
            'error' => $error,
        ];
    }

    private static function normalizeStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'found', 'registered', 'taken' => 'found',
            'not found', 'not registered', 'available' => 'not_found',
            'skipped' => 'skipped',
            'error' => 'error',
            default => strtolower(trim($status)),
        };
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeMetadataPayload(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private static function summarizeMetadata(array $metadata): string
    {
        $lines = [];
        foreach ($metadata as $key => $value) {
            if ($key === 'external_links') {
                $value = self::normalizeExternalLinks($value);
            }

            if (is_array($value)) {
                $value = implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value));
            }

            if ($value === null) {
                continue;
            }

            $string = trim((string) $value);
            if ($string === '') {
                continue;
            }

            $lines[] = $key . ': ' . $string;
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<int, string>
     */
    private static function normalizeExternalLinks(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $links = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $string = trim((string) $item);
            if ($string !== '') {
                $links[] = $string;
            }
        }

        return array_values(array_unique($links));
    }

    /**
     * @param array<string, mixed> $metadata
     * @param mixed $externalLinks
     * @return array<string, mixed>
     */
    private static function normalizeMetadataForOutput(
        array $metadata,
        string $mode,
        string $target,
        string $platform,
        string $normalizedStatus,
        ?string $profileUrl,
        string $reason,
        array $externalLinks,
        string $extra,
    ): array {
        $normalized = [
            'display_name' => null,
            'username' => $mode === 'username' ? $target : null,
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
            'observed_metadata_level' => null,
            'platform' => $platform,
        ];

        foreach ($metadata as $key => $value) {
            $normalized[$key] = $value;
        }

        $normalized = PublicMetadataNormalizer::normalize($normalized);
        $normalized['external_links'] = PublicMetadataNormalizer::normalizePublicLinks($externalLinks);
        $normalized['platform'] = $platform;
        $normalized['status_detail'] = self::normalizeStatusDetail($metadata['status_detail'] ?? null, $normalizedStatus, $reason);

        $existingEvidence = self::normalizeExternalLinks($normalized['evidence'] ?? []);
        $normalized['evidence'] = array_values(array_unique(array_merge(
            $existingEvidence,
            self::deriveEvidence($profileUrl, $normalized, $extra)
        )));

        $existingObservedLevel = is_int($normalized['observed_metadata_level'] ?? null)
            ? (int) $normalized['observed_metadata_level']
            : null;
        $derivedObservedLevel = self::deriveObservedMetadataLevel($normalizedStatus, $profileUrl, $normalized);
        $normalized['observed_metadata_level'] = $existingObservedLevel !== null
            ? max($existingObservedLevel, $derivedObservedLevel)
            : $derivedObservedLevel;

        return $normalized;
    }

    private static function resolveProfileUrlForOutput(?string $profileUrl, string $url, string $normalizedStatus, string $mode, string $target): ?string
    {
        if ($normalizedStatus !== 'found') {
            return null;
        }

        $explicitProfileUrl = self::sanitizePublicProfileUrl($profileUrl, false, $target);
        if ($explicitProfileUrl !== null) {
            return $explicitProfileUrl;
        }

        if ($mode !== 'username') {
            return null;
        }

        if (trim($url) === '') {
            return null;
        }

        return self::sanitizePublicProfileUrl($url, true, $target);
    }

    private static function sanitizePublicProfileUrl(?string $candidate, bool $requireTargetEvidence, string $target): ?string
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

        foreach (self::blockedProfileUrlNeedles($requireTargetEvidence) as $needle) {
            if (str_contains($path, $needle) || str_contains($query, trim($needle, '/'))) {
                return null;
            }
        }

        return self::buildAbsoluteUrl($parsed, $scheme, $host);
    }

    /**
     * @return array<int, string>
     */
    private static function blockedProfileUrlNeedles(bool $strict): array
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
     * @param array<string, mixed> $parsed
     */
    private static function buildAbsoluteUrl(array $parsed, string $scheme, string $host): string
    {
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

    private static function normalizeStatusDetail(mixed $existingStatusDetail, string $normalizedStatus, string $reason): string
    {
        if (is_string($existingStatusDetail) && trim($existingStatusDetail) !== '') {
            return trim($existingStatusDetail);
        }

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
    private static function deriveEvidence(?string $profileUrl, array $metadata, string $extra): array
    {
        $evidence = [];
        if ($profileUrl !== null) {
            $evidence[] = 'profile_url';
        }
        if (trim($extra) !== '') {
            $evidence[] = 'legacy_extra';
        }

        foreach (['display_name', 'avatar_url', 'bio', 'website_url', 'public_email', 'followers', 'following', 'posts_count', 'created_at', 'last_active_at'] as $field) {
            $value = $metadata[$field] ?? null;
            if ($value !== null && $value !== '' && $value !== []) {
                $evidence[] = $field;
            }
        }

        if (($metadata['external_links'] ?? []) !== []) {
            $evidence[] = 'external_links';
        }

        if (is_array($metadata['sources'] ?? null)) {
            foreach ($metadata['sources'] as $source) {
                if (is_scalar($source) && trim((string) $source) !== '') {
                    $evidence[] = trim((string) $source);
                }
            }
        }

        return array_values(array_unique($evidence));
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private static function deriveObservedMetadataLevel(string $normalizedStatus, ?string $profileUrl, array $metadata): int
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
        $evidenceCount = count(self::normalizeExternalLinks($metadata['evidence'] ?? []));
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
     * @param array<string, mixed> $metadata
     */
    private static function deriveConfidence(string $normalizedStatus, ?string $profileUrl, array $metadata): ?float
    {
        return match ($normalizedStatus) {
            'error', 'skipped' => 0.0,
            'not_found' => 0.95,
            'found' => self::deriveFoundConfidence($profileUrl, $metadata),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private static function deriveFoundConfidence(?string $profileUrl, array $metadata): float
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
}
