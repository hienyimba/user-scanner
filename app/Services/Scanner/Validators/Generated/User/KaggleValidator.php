<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/creator/kaggle.py
// parity-class: generated

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class KaggleValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'kaggle';
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
        return 'Kaggle';
    }

    public function siteUrl(): string
    {
        return 'https://www.kaggle.com/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.kaggle.com/{$target}";
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

        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        if ($status === 200) {
            return ['Taken', ''];
        }

        if ($status === 404) {
            return ['Available', ''];
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

        $profileTitle = $this->extractProfileTitle($response->body());
        $displayName = $profileTitle !== null ? $this->extractDisplayNameFromTitle($profileTitle) : null;
        if ($displayName === null) {
            return $fallback;
        }

        $metadata = [
            'username' => $target,
            'display_name' => $displayName,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['profile_html']),
        ];
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

    private function extractDisplayNameFromTitle(string $title): ?string
    {
        if (preg_match('/^([^\|]+)\|/is', $title, $matches) !== 1) {
            return null;
        }

        $displayName = trim($matches[1]);

        return $displayName !== '' ? $displayName : null;
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
