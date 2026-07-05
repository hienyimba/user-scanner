<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ProducthuntValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'producthunt'; }
    public function category(): string { return 'creator'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Producthunt'; }
    public function siteUrl(): string { return 'https://www.producthunt.com/@{user}'; }
    protected function requestUrl(string $target): string { return "https://www.producthunt.com/@{$target}"; }
    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept-Language' => 'en-US,en;q=0.9',
            'upgrade-insecure-requests' => '1',
            'sec-fetch-site' => 'none',
            'sec-fetch-mode' => 'navigate',
            'sec-fetch-user' => '?1',
            'sec-fetch-dest' => 'document',
            'sec-ch-ua' => '"Google Chrome";v="143", "Chromium";v="143", "Not A(Brand";v="24"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'cache-control' => 'max-age=0',
        ];
    }
    public function check(string $target, array $options = []): ScanResult
    {
        if (strlen($target) < 2 || strlen($target) > 32) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Length must be 2-32 characters.', mode: $this->mode(), key: $this->key());
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $target)) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Only use letters, numbers, and underscores.', mode: $this->mode(), key: $this->key());
        }
        return parent::check($target, $options);
    }
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        return match ($response->status()) {
            404 => ['Available', ''],
            200 => ['Taken', ''],
            default => ['Error', 'HTTP ' . $response->status()],
        };
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

        $profileTitle = $this->extractProfileTitle($response->body());
        $ld = $this->extractJsonLd($response->body());
        $metadata = [
            'username' => $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['profile_html', 'jsonld']),
        ];

        $displayName = $this->cleanString($ld['name'] ?? null)
            ?? $this->extractDisplayName($profileTitle)
            ?? $this->extractDisplayNameFromMetaText($response->body());
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }

        $profilePageUrl = $this->cleanString($ld['url'] ?? null);
        if ($profilePageUrl !== null) {
            $metadata['producthunt_url'] = $profilePageUrl;
            $metadata['external_links'] = [$profilePageUrl];
        }

        if ($profileTitle !== null) {
            $metadata['profile_title'] = $profileTitle;
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
        if (is_string($metadata['producthunt_url'] ?? null) && $metadata['producthunt_url'] !== '') {
            $summary['URL'] = $metadata['producthunt_url'];
        }

        return $this->metadataSummary($summary);
    }

    private function extractProfileTitle(string $html): ?string
    {
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches) !== 1) {
            return null;
        }

        $title = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));

        return $title !== '' ? $title : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractJsonLd(string $html): ?array
    {
        if (preg_match('/<script[^>]*type=\"application\/ld\+json\"[^>]*>(.*?)<\/script>/is', $html, $matches) !== 1) {
            return null;
        }

        $decoded = json_decode($matches[1], true);
        if (is_array($decoded) && array_is_list($decoded)) {
            $decoded = $decoded[0] ?? null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function extractDisplayName(?string $title): ?string
    {
        if ($title === null) {
            return null;
        }

        if (preg_match('/^(.*?)(?:&#x27;s|\'s) profile/is', $title, $matches) === 1) {
            $name = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));

            return $name !== '' ? $name : null;
        }

        return null;
    }

    private function extractDisplayNameFromMetaText(string $html): ?string
    {
        if (preg_match('/See what kind of products\s+([^\(]+)/is', $html, $matches) !== 1) {
            return null;
        }

        $name = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));

        return $name !== '' ? $name : null;
    }

    private function cleanString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
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
