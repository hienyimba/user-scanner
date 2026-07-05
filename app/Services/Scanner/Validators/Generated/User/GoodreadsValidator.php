<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/goodreads.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class GoodreadsValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'goodreads';
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
        return 'Goodreads';
    }

    public function siteUrl(): string
    {
        return 'https://www.goodreads.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.goodreads.com/{$target}";
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
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return [];
        }

        $html = $response->body();
        $metadata = [
            'username' => $target,
            'sources' => ['profile_html'],
        ];

        if (preg_match('/<meta[^>]*property=["\']og:title["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $matches) === 1) {
            $displayName = trim(preg_replace('/\s*\(.*$/', '', html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5)) ?? '');
            if ($displayName !== '') {
                $metadata['display_name'] = $displayName;
            }
        }

        if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches) === 1) {
            $title = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));
            if ($title !== '') {
                $metadata['profile_title'] = $title;
            }
        }

        return $metadata;
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
}
