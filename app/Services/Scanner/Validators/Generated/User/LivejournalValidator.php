<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/livejournal.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class LivejournalValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'livejournal';
    }

    public function category(): string
    {
        return 'social';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Livejournal';
    }

    public function siteUrl(): string
    {
        return 'https://{user}.livejournal.com';
    }

    protected function requestUrl(string $target): string
    {
        return "https://{$target}.livejournal.com";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $response = $this->makeRequest($target, $options);

            if ($response->status() === 403) {
                $status = 'Taken';

                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    $status,
                    '',
                    $this->buildExtraMetadata($response, $target, $status),
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->buildStructuredMetadata($response, $target, $status),
                );
            }

            $challenge = $this->detectBlockedOrChallenged($response);
            [$status, $reason] = $challenge ?? $this->parseConnectorResponse($response, $target);
            $structuredMetadata = $challenge === null ? $this->buildStructuredMetadata($response, $target, $status) : [];
            $extra = $challenge === null ? $this->buildExtraMetadata($response, $target, $status) : '';

            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                $status,
                $reason,
                $extra,
                mode: $this->mode(),
                key: $this->key(),
                metadata: $structuredMetadata,
            );
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = match (true) {
                str_contains($message, 'timed out') => 'Request timeout',
                str_contains($message, 'ssl_read'),
                str_contains($message, 'unexpected eof while reading') => 'TLS/anti-bot layer closed the connection unexpectedly',
                default => $e->getMessage(),
            };

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();

        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        if ($status === 403) {
            return ['Taken', ''];
        }

        if (in_array($status, [301, 302, 404, 410], true)) {
            return ['Available', ''];
        }

        if ($status === 200) {
            return ['Taken', ''];
        }

        return ['Error', 'Unexpected response body'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        $fallback = parent::buildStructuredMetadata($response, $target, $status);
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return $fallback;
        }

        if ($response->status() === 403) {
            return array_replace($fallback, [
                'username' => $target,
                'account_status' => 'suspended_or_forbidden',
                'sources' => $this->mergeSources($fallback['sources'] ?? [], ['profile_html']),
            ]);
        }

        $journal = $this->extractJournalData($response->body());
        if (!is_array($journal)) {
            return $fallback;
        }

        $metadata = [
            'username' => $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['html_hydration']),
        ];

        $uid = $this->normalizeInteger($journal['id'] ?? null);
        if ($uid !== null) {
            $metadata['livejournal_uid'] = $uid;
        }

        $displayName = $this->cleanString($journal['display_username'] ?? null);
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }

        if (is_bool($journal['is_paid'] ?? null)) {
            $metadata['is_paid'] = $journal['is_paid'];
        }

        if (is_bool($journal['is_community'] ?? null)) {
            $metadata['is_community'] = $journal['is_community'];
            $metadata['account_type'] = $journal['is_community'] ? 'community' : 'user';
        }

        return array_replace($fallback, $metadata);
    }

    protected function buildExtraMetadata(Response $response, string $target, string $status): string
    {
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return '';
        }

        $metadata = $this->buildStructuredMetadata($response, $target, $status);
        $summary = [];

        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (isset($metadata['is_paid'])) {
            $summary['Paid Account'] = (bool) $metadata['is_paid'] ? 'Yes' : 'No';
        }
        if (isset($metadata['is_community'])) {
            $summary['Community'] = (bool) $metadata['is_community'] ? 'Yes' : 'No';
        }
        if (is_string($metadata['account_status'] ?? null) && $metadata['account_status'] !== '') {
            $summary['Status'] = str_replace('_', ' ', $metadata['account_status']);
        }

        return $this->metadataSummary($summary);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractJournalData(string $html): ?array
    {
        if (preg_match('/Site\.journal\s*=\s*({.+?});/is', $html, $matches) !== 1) {
            return null;
        }

        $decoded = json_decode($matches[1], true);

        return is_array($decoded) ? $decoded : null;
    }

    private function cleanString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param mixed $existing
     * @param array<int, string> $newSources
     * @return array<int, string>
     */
    private function mergeSources(mixed $existing, array $newSources): array
    {
        $sources = is_array($existing) ? $existing : [];
        $merged = [];

        foreach (array_merge($sources, $newSources) as $source) {
            if (!is_string($source) || trim($source) === '') {
                continue;
            }

            $merged[] = trim($source);
        }

        return array_values(array_unique($merged));
    }
}
