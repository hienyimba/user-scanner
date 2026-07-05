<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class CodebergValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'codeberg';
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
        return 'Codeberg';
    }

    public function siteUrl(): string
    {
        return 'https://codeberg.org/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://codeberg.org/api/v1/users/{$target}";
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
            'User-Agent' => 'Mozilla/5.0',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = strtolower($response->body());

        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        $availableStatuses = [404];
        $takenStatuses = [200];
        $availableIndicators = [];
        $takenIndicators = [];

        if ($this->mode() === 'username') {
            if (in_array($status, $availableStatuses, true)) {
                return ['Available', ''];
            }
            if (in_array($status, $takenStatuses, true)) {
                return ['Taken', ''];
            }
            foreach ($takenIndicators as $needle) {
                if ($needle !== '' && str_contains($body, $needle)) {
                    return ['Taken', ''];
                }
            }
            foreach ($availableIndicators as $needle) {
                if ($needle !== '' && str_contains($body, $needle)) {
                    return ['Available', ''];
                }
            }

            return ['Error', $this->key() . ': indeterminate username response (HTTP ' . $status . ')'];
        }

        if (in_array($status, $takenStatuses, true)) {
            return ['Registered', ''];
        }
        if (in_array($status, $availableStatuses, true)) {
            return ['Not Registered', ''];
        }
        foreach ($takenIndicators as $needle) {
            if ($needle !== '' && str_contains($body, $needle)) {
                return ['Registered', ''];
            }
        }
        foreach ($availableIndicators as $needle) {
            if ($needle !== '' && str_contains($body, $needle)) {
                return ['Not Registered', ''];
            }
        }

        return ['Error', $this->key() . ': indeterminate email response (HTTP ' . $status . ')'];
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
            'username' => $this->cleanString($data['login'] ?? null) ?? $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['api_json']),
        ];

        $codebergId = $this->normalizeInteger($data['id'] ?? null);
        if ($codebergId !== null) {
            $metadata['codeberg_id'] = $codebergId;
        }

        $displayName = $this->cleanString($data['full_name'] ?? null);
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }

        $publicEmail = $this->cleanString($data['email'] ?? null);
        if ($publicEmail !== null && str_contains($publicEmail, '@')) {
            $metadata['public_email'] = $publicEmail;
        }

        $createdAt = $this->normalizeDate($data['created'] ?? null);
        if ($createdAt !== null) {
            $metadata['created_at'] = $createdAt;
        }

        $location = $this->cleanString($data['location'] ?? null);
        if ($location !== null) {
            $metadata['location'] = $location;
        }

        $websiteUrl = $this->cleanString($data['website'] ?? null);
        if ($websiteUrl !== null) {
            $metadata['website_url'] = $websiteUrl;
            $metadata['external_links'] = [$websiteUrl];
        }

        $avatarUrl = $this->cleanString($data['avatar_url'] ?? null);
        if ($avatarUrl !== null) {
            $metadata['avatar_url'] = $avatarUrl;
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
        if (is_string($metadata['public_email'] ?? null) && $metadata['public_email'] !== '') {
            $summary['Email'] = $metadata['public_email'];
        }
        if (is_string($metadata['created_at'] ?? null) && $metadata['created_at'] !== '') {
            $summary['Created'] = $metadata['created_at'];
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (is_string($metadata['website_url'] ?? null) && $metadata['website_url'] !== '') {
            $summary['Website'] = $metadata['website_url'];
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

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $normalized = $this->cleanString($value);
        if ($normalized === null) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($normalized))->format(\DateTimeInterface::ATOM);
        } catch (\Throwable) {
            return null;
        }
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
