<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/creator/boosty.py
// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BoostyValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'boosty';
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
        return 'Boosty';
    }

    public function siteUrl(): string
    {
        return 'https://boosty.to/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.boosty.to/v1/blog/{$target}";
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
        if (!is_array($data) || !is_array($data['owner'] ?? null)) {
            return $fallback;
        }

        $owner = $data['owner'];
        $count = is_array($data['count'] ?? null) ? $data['count'] : [];
        $apps = is_array($owner['externalApps'] ?? null) ? $owner['externalApps'] : [];

        $metadata = [
            'username' => $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['api_json']),
        ];

        $boostyId = $this->normalizeInteger($owner['id'] ?? null);
        if ($boostyId !== null) {
            $metadata['boosty_id'] = $boostyId;
        }

        $displayName = $this->cleanString($owner['name'] ?? null);
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }

        $title = $this->cleanString($data['title'] ?? null);
        if ($title !== null) {
            $metadata['profile_title'] = $title;
        }

        $subscribers = $this->normalizeInteger($count['subscribers'] ?? null);
        if ($subscribers !== null) {
            $metadata['subscribers_count'] = $subscribers;
            $metadata['followers'] = $subscribers;
        }

        $posts = $this->normalizeInteger($count['posts'] ?? null);
        if ($posts !== null) {
            $metadata['posts_count'] = $posts;
        }

        if ((bool) data_get($apps, 'discord.hasAccount')) {
            $metadata['has_discord'] = true;
        }
        if ((bool) data_get($apps, 'telegram.hasAccount')) {
            $metadata['has_telegram'] = true;
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
        if (isset($metadata['subscribers_count'])) {
            $summary['Subscribers'] = (string) $metadata['subscribers_count'];
        }
        if (isset($metadata['posts_count'])) {
            $summary['Posts'] = (string) $metadata['posts_count'];
        }
        if (is_string($metadata['profile_title'] ?? null) && $metadata['profile_title'] !== '') {
            $summary['Title'] = $metadata['profile_title'];
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
