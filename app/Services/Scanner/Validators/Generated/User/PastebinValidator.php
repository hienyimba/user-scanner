<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/other/pastebin.py
// parity-class: generated

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class PastebinValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'pastebin';
    }

    public function category(): string
    {
        return 'other';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Pastebin';
    }

    public function siteUrl(): string
    {
        return 'https://pastebin.com/u/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://pastebin.com/u/{$target}";
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
        return [];
    }

    protected function requestQuery(string $target): array
    {
        return [];
    }


    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();

        if ($status === 200) {
            if (str_contains($body, 'info-bar') || str_contains($body, 'user-icon')) {
                return ['Taken', ''];
            }

            return ['Available', ''];
        }

        if ($status === 404) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected status code: ' . $status];
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

        $html = $response->body();
        $metadata = [
            'username' => $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['profile_html']),
        ];

        $avatarUrl = $this->extractAbsoluteAvatarUrl($html);
        if ($avatarUrl !== null) {
            $metadata['avatar_url'] = $avatarUrl;
        }

        if (str_contains($html, 'class="pro"')) {
            $metadata['is_pro'] = true;
        }

        $views = $this->extractInteger('#<span class="views"[^>]*>([\d,]+)</span>#i', $html);
        if ($views !== null) {
            $metadata['views'] = $views;
        }

        $allViews = $this->extractInteger('#<span class="views -all"[^>]*>([\d,]+)</span>#i', $html);
        if ($allViews !== null) {
            $metadata['all_views'] = $allViews;
        }

        $rating = $this->extractDecodedString('#<span class="rating"[^>]*>([^\s<]+)</span>#i', $html);
        if ($rating !== null) {
            $metadata['rating'] = $rating;
        }

        $createdAt = $this->extractDecodedString('#class="date-text"\s+title="([^"]+)"#i', $html);
        if ($createdAt !== null) {
            $metadata['created_at'] = $createdAt;
        }

        $websiteUrl = $this->extractDecodedString('#<a[^>]+class="web"[^>]*href="([^"]+)"#i', $html);
        if ($websiteUrl !== null) {
            $metadata['website_url'] = $websiteUrl;
            $metadata['external_links'] = array_values(array_unique(array_merge(
                is_array($fallback['external_links'] ?? null) ? $fallback['external_links'] : [],
                [$websiteUrl]
            )));
        }

        $location = $this->extractDecodedString('#<span[^>]+class="location"[^>]*>([\s\S]*?)</span>#i', $html);
        if ($location !== null) {
            $metadata['location'] = trim(strip_tags($location));
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
            $summary['Location'] = $metadata['location'];
        }
        if (isset($metadata['views'])) {
            $summary['Views'] = (string) $metadata['views'];
        }
        if (isset($metadata['all_views'])) {
            $summary['All Views'] = (string) $metadata['all_views'];
        }
        if (isset($metadata['is_pro'])) {
            $summary['Pro User'] = (bool) $metadata['is_pro'] ? 'Yes' : 'No';
        }

        return $this->metadataSummary($summary);
    }

    private function extractAbsoluteAvatarUrl(string $html): ?string
    {
        $avatar = $this->extractDecodedString('#<div class="user-icon">\s*<img src="([^"]+)"#i', $html);
        if ($avatar === null || str_contains($avatar, 'guest.png')) {
            return null;
        }

        if (str_starts_with($avatar, '/')) {
            return 'https://pastebin.com' . $avatar;
        }

        return $avatar;
    }

    private function extractInteger(string $pattern, string $html): ?int
    {
        if (preg_match($pattern, $html, $matches) !== 1) {
            return null;
        }

        $value = str_replace(',', '', $matches[1]);

        return preg_match('/^\d+$/', $value) === 1 ? (int) $value : null;
    }

    private function extractDecodedString(string $pattern, string $html): ?string
    {
        if (preg_match($pattern, $html, $matches) !== 1) {
            return null;
        }

        $value = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));

        return $value !== '' ? $value : null;
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
