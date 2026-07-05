<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/creator/behance.py
// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BehanceValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'behance';
    }

    public function category(): string
    {
        return 'creator';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Behance';
    }

    public function siteUrl(): string
    {
        return 'https://www.behance.net/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.behance.net/{$target}/appreciated";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();

        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        if ($status === 404) {
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

        $storeState = $this->extractStoreState($response->body());
        $userData = is_array(data_get($storeState, 'profile.user')) ? data_get($storeState, 'profile.user') : [];
        if (!is_array($userData) || $userData === []) {
            return $fallback;
        }

        $stats = is_array($userData['stats'] ?? null) ? $userData['stats'] : [];
        $metadata = [
            'username' => $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['html_hydration']),
        ];

        $displayName = $this->cleanString($userData['displayName'] ?? null);
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }

        $location = $this->cleanString($userData['location'] ?? null);
        if ($location !== null) {
            $metadata['location'] = $location;
        }

        $company = $this->cleanString($userData['company'] ?? null);
        if ($company !== null) {
            $metadata['company'] = $company;
        }

        $followers = $this->normalizeInteger($stats['followers'] ?? null);
        if ($followers !== null) {
            $metadata['followers'] = $followers;
        }

        $following = $this->normalizeInteger($stats['following'] ?? null);
        if ($following !== null) {
            $metadata['following'] = $following;
        }

        $viewsCount = $this->normalizeInteger($stats['views'] ?? null);
        if ($viewsCount !== null) {
            $metadata['views_count'] = $viewsCount;
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
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (is_string($metadata['company'] ?? null) && $metadata['company'] !== '') {
            $summary['Company'] = $metadata['company'];
        }
        if (isset($metadata['followers'])) {
            $summary['Followers'] = (string) $metadata['followers'];
        }
        if (isset($metadata['following'])) {
            $summary['Following'] = (string) $metadata['following'];
        }
        if (isset($metadata['views_count'])) {
            $summary['Views'] = (string) $metadata['views_count'];
        }

        return $this->metadataSummary($summary);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractStoreState(string $html): ?array
    {
        if (preg_match('/<script[^>]*id="beconfig-store_state"[^>]*>(.*?)<\/script>/is', $html, $matches) !== 1) {
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
