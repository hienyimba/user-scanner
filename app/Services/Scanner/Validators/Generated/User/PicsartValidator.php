<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/picsart.py
// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class PicsartValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'picsart';
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
        return 'Picsart';
    }

    public function siteUrl(): string
    {
        return 'https://picsart.com/u/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.picsart.com/users/show/{$target}.json";
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
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
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
            'username' => $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['api_json']),
        ];

        $id = $this->normalizeInteger($data['id'] ?? null);
        if ($id !== null) {
            $metadata['picsart_id'] = $id;
        }

        $displayName = $this->cleanString($data['name'] ?? null);
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }

        $statusMessage = $this->cleanString($data['status_message'] ?? null);
        if ($statusMessage !== null) {
            $metadata['bio'] = $statusMessage;
        }

        $followers = $this->normalizeInteger($data['followers_count'] ?? null);
        if ($followers !== null) {
            $metadata['followers'] = $followers;
        }

        $following = $this->normalizeInteger($data['following_count'] ?? null);
        if ($following !== null) {
            $metadata['following'] = $following;
        }

        $likes = $this->normalizeInteger($data['likes_count'] ?? null);
        if ($likes !== null) {
            $metadata['likes_count'] = $likes;
        }

        $photos = $this->normalizeInteger($data['photos_count'] ?? null);
        if ($photos !== null) {
            $metadata['photos_count'] = $photos;
            $metadata['posts_count'] = $photos;
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
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Status Message'] = $metadata['bio'];
        }
        if (isset($metadata['followers'])) {
            $summary['Followers'] = (string) $metadata['followers'];
        }
        if (isset($metadata['following'])) {
            $summary['Following'] = (string) $metadata['following'];
        }
        if (isset($metadata['likes_count'])) {
            $summary['Likes'] = (string) $metadata['likes_count'];
        }
        if (isset($metadata['photos_count'])) {
            $summary['Photos'] = (string) $metadata['photos_count'];
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
