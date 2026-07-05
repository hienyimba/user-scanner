<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/music/lastfm.py
// parity-class: generated

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class LastfmValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'lastfm';
    }

    public function category(): string
    {
        return 'music';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Lastfm';
    }

    public function siteUrl(): string
    {
        return 'https://www.last.fm/user/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.last.fm/user/{$target}";
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
        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        $status = $response->status();

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

        $html = $response->body();
        $metadata = [
            'username' => $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['profile_html']),
        ];

        $displayName = $this->matchOne('/class="header-title-display-name">\s*([^<\n\r]+)/i', $html);
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }

        $scrobblingSince = $this->matchOne('/scrobbling since\s*([^<\n\r]+)/i', $html);
        if ($scrobblingSince !== null) {
            $metadata['scrobbling_since'] = $scrobblingSince;
            $normalizedDate = $this->normalizeDate($scrobblingSince);
            if ($normalizedDate !== null) {
                $metadata['created_at'] = $normalizedDate;
            }
        }

        $scrobbles = $this->normalizeMetric($this->matchOne('/Scrobbles.*?<p[^>]*>.*?<a[^>]*>([^<]+)<\/a>/is', $html));
        if ($scrobbles !== null) {
            $metadata['scrobbles_count'] = $scrobbles;
            $metadata['posts_count'] = $scrobbles;
        }

        $artists = $this->normalizeMetric($this->matchOne('/Artists.*?<p[^>]*>.*?<a[^>]*>([^<]+)<\/a>/is', $html));
        if ($artists !== null) {
            $metadata['artists_count'] = $artists;
        }

        $avatarUrl = $this->matchOne('/src="([^"]+)"[^>]*alt="Avatar for [^"]+"[^>]*itemprop="image"/is', $html);
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
        if (is_string($metadata['scrobbling_since'] ?? null) && $metadata['scrobbling_since'] !== '') {
            $summary['Scrobbling Since'] = $metadata['scrobbling_since'];
        }
        if (isset($metadata['scrobbles_count'])) {
            $summary['Scrobbles'] = (string) $metadata['scrobbles_count'];
        }
        if (isset($metadata['artists_count'])) {
            $summary['Artists'] = (string) $metadata['artists_count'];
        }

        return $this->metadataSummary($summary);
    }

    private function matchOne(string $pattern, string $html): ?string
    {
        if (preg_match($pattern, $html, $matches) !== 1) {
            return null;
        }

        $value = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));

        return $value !== '' ? $value : null;
    }

    private function normalizeMetric(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/[^\d]/', '', $value);
        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        return (int) $normalized;
    }

    private function normalizeDate(string $value): ?string
    {
        try {
            return (new \DateTimeImmutable($value))->format(\DateTimeInterface::ATOM);
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
