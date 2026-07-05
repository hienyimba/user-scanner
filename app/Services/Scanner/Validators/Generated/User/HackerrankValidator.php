<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/hackerrank.py
// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class HackerrankValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'hackerrank';
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
        return 'Hackerrank';
    }

    public function siteUrl(): string
    {
        return 'https://www.hackerrank.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.hackerrank.com/rest/contests/master/hackers/{$target}/profile";
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

        $model = data_get($response->json(), 'model');
        if (!is_array($model)) {
            return $fallback;
        }

        $metadata = [
            'username' => $this->cleanString($model['username'] ?? null) ?? $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['api_json']),
        ];

        $hackerrankId = $this->normalizeInteger($model['id'] ?? null);
        if ($hackerrankId !== null) {
            $metadata['hackerrank_id'] = $hackerrankId;
        }

        $country = $this->cleanString($model['country'] ?? null);
        if ($country !== null) {
            $metadata['location'] = $country;
        }

        $school = $this->cleanString($model['school'] ?? null);
        if ($school !== null) {
            $metadata['school'] = $school;
        }

        $createdAt = $this->normalizeDate($model['created_at'] ?? null);
        if ($createdAt !== null) {
            $metadata['created_at'] = $createdAt;
        }

        $level = $this->normalizeInteger($model['level'] ?? null);
        if ($level !== null) {
            $metadata['hackerrank_level'] = $level;
        }

        $company = $this->cleanString($model['company'] ?? null);
        if ($company !== null) {
            $metadata['company'] = $company;
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

        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Country'] = $metadata['location'];
        }
        if (is_string($metadata['school'] ?? null) && $metadata['school'] !== '') {
            $summary['School'] = $metadata['school'];
        }
        if (is_string($metadata['created_at'] ?? null) && $metadata['created_at'] !== '') {
            $summary['Created'] = $metadata['created_at'];
        }
        if (isset($metadata['hackerrank_level'])) {
            $summary['Level'] = (string) $metadata['hackerrank_level'];
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
