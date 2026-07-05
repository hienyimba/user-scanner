<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class PatreonValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'patreon'; }
    public function category(): string { return 'creator'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Patreon'; }
    public function siteUrl(): string { return 'https://www.patreon.com/{user}'; }
    protected function timeoutSeconds(): int { return 20; }
    protected function requestUrl(string $target): string { return "https://www.patreon.com/{$target}"; }
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

        $ld = $this->extractJsonLd($response->body());
        $metadata = [
            'username' => $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['jsonld', 'profile_html']),
        ];

        $displayName = $this->cleanString($ld['name'] ?? null);
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }

        $patreonUrl = $this->cleanString($ld['url'] ?? null);
        if ($patreonUrl !== null) {
            $metadata['patreon_url'] = $patreonUrl;
            $metadata['external_links'] = [$patreonUrl];
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
        if (is_string($metadata['patreon_url'] ?? null) && $metadata['patreon_url'] !== '') {
            $summary['URL'] = $metadata['patreon_url'];
        }

        return $this->metadataSummary($summary);
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
