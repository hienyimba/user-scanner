<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/community/instructables.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class InstructablesValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'instructables';
    }

    public function category(): string
    {
        return 'community';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Instructables';
    }

    public function siteUrl(): string
    {
        return 'https://www.instructables.com/member/{user}/';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.instructables.com/member/{$target}/";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();

        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status === 200) {
            return ['Taken', ''];
        }

        return ['Error', 'Unexpected response status: ' . $status];
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
        $displayName = $this->extractDisplayName($profileTitle, 'Instructables');

        $metadata = [
            'username' => $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['profile_html']),
        ];

        if ($profileTitle !== null) {
            $metadata['profile_title'] = $profileTitle;
        }

        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
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
            $summary['Description'] = $metadata['bio'];
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

    private function extractDisplayName(?string $title, string $siteName): ?string
    {
        if ($title === null) {
            return null;
        }

        $patterns = [
            '/\s*-\s*' . preg_quote($siteName, '/') . '\s*$/i',
            '/\s*\|\s*' . preg_quote($siteName, '/') . '\s*$/i',
        ];

        foreach ($patterns as $pattern) {
            $cleaned = preg_replace($pattern, '', $title);
            if (is_string($cleaned)) {
                $cleaned = trim($cleaned);
                if ($cleaned !== '') {
                    return $cleaned;
                }
            }
        }

        return $title;
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
