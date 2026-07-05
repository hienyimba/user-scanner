<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/coderwall.py
// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class CoderwallValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'coderwall';
    }

    public function category(): string
    {
        return 'dev';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Coderwall';
    }

    public function siteUrl(): string
    {
        return 'https://coderwall.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://coderwall.com/{$target}.json";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
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

        $data = $response->json();
        if (!is_array($data)) {
            return $fallback;
        }

        $metadata = [
            'username' => $this->cleanString($data['username'] ?? null) ?? $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['api_json']),
        ];

        $coderwallId = $this->normalizeInteger($data['id'] ?? null);
        if ($coderwallId !== null) {
            $metadata['coderwall_id'] = $coderwallId;
        }

        $displayName = $this->cleanString($data['name'] ?? null);
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }

        $location = $this->cleanString($data['location'] ?? null);
        if ($location !== null) {
            $metadata['location'] = $location;
        }

        $karma = $this->normalizeInteger($data['karma'] ?? null);
        if ($karma !== null) {
            $metadata['karma'] = $karma;
        }

        $company = $this->cleanString($data['company'] ?? null);
        if ($company !== null) {
            $metadata['company'] = $company;
        }

        $bio = $this->cleanString($data['about'] ?? null);
        if ($bio !== null) {
            $metadata['bio'] = $bio;
        }

        $avatarUrl = $this->cleanString($data['thumbnail'] ?? null);
        if ($avatarUrl !== null) {
            $metadata['avatar_url'] = $avatarUrl;
        }

        $accounts = is_array($data['accounts'] ?? null) ? $data['accounts'] : [];
        foreach ($accounts as $platform => $handle) {
            if (!is_string($platform)) {
                continue;
            }

            $normalizedHandle = $this->cleanString($handle);
            if ($normalizedHandle === null) {
                continue;
            }

            $metadata[strtolower($platform) . '_handle'] = $normalizedHandle;
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
        if (isset($metadata['karma'])) {
            $summary['Karma'] = (string) $metadata['karma'];
        }
        if (is_string($metadata['company'] ?? null) && $metadata['company'] !== '') {
            $summary['Company'] = $metadata['company'];
        }

        return $this->metadataSummary($summary);
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

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^-?\d+(?:\.\d+)?$/', $value) === 1) {
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
