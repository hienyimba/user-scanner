<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class LiberapayValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'liberapay';
    }

    public function category(): string
    {
        return 'donation';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Liberapay';
    }

    public function siteUrl(): string
    {
        return 'https://en.liberapay.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://en.liberapay.com/{$target}";
    }

    protected function requestHeaders(): array
    {
        return [
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'accept-language' => 'en-Us,pt;q=0.6',
            'cache-control' => 'no-cache',
            'pragma' => 'no-cache',
            'priority' => 'u=0, i',
            'sec-ch-ua' => '"Chromium";v="142", "Brave";v="142", "Not_A Brand";v="99"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'sec-fetch-dest' => 'document',
            'sec-fetch-mode' => 'navigate',
            'sec-fetch-site' => 'none',
            'sec-fetch-user' => '?1',
            'sec-gpc' => '1',
            'upgrade-insecure-requests' => '1',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        return match ($response->status()) {
            404, 410 => ['Available', ''],
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

        $title = $this->extractProfileTitle($response->body());
        $displayName = $this->extractDisplayName($title, $target);
        if ($title === null && $displayName === null) {
            return $fallback;
        }

        $metadata = [
            'username' => $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['profile_html']),
        ];
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }
        if ($title !== null) {
            $metadata['profile_title'] = $title;
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
        if (is_string($metadata['profile_title'] ?? null) && $metadata['profile_title'] !== '') {
            $summary['Title'] = $metadata['profile_title'];
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

    private function extractDisplayName(?string $title, string $target): ?string
    {
        if ($title === null) {
            return null;
        }

        $name = trim(preg_split('/(?:&#39;s|\'s) profile/i', $title)[0] ?? '');
        if ($name === '' || strtolower($name) === strtolower($target)) {
            return null;
        }

        return $name;
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
