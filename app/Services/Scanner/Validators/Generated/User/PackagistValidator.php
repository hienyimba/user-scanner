<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/packagist.py
// parity-class: generated

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class PackagistValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'packagist';
    }

    public function category(): string
    {
        return 'dev';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Packagist';
    }

    public function siteUrl(): string
    {
        return 'https://packagist.org/users/{user}/';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://packagist.org/users/{$target}/";
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
        if ($status === 200) {
            return ['Taken', ''];
        }

        if ($status === 404) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected status: ' . $status];
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

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) === 1) {
            $name = trim(str_replace('- Packagist', '', html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5)));
            if ($name !== '') {
                $metadata['display_name'] = $name;
            }
        }

        if (preg_match_all('#/packages/([^/]+/[^/"]+)#i', $html, $matches) !== false) {
            $packages = array_values(array_unique(array_filter(
                array_map(static fn (string $value): string => trim($value), $matches[1]),
                static fn (string $value): bool => $value !== '' && !str_contains($value, 'submit')
            )));

            if ($packages !== []) {
                $metadata['packages_count'] = count($packages);
                $metadata['posts_count'] = count($packages);
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
        if (isset($metadata['packages_count'])) {
            $summary['Packages'] = (string) $metadata['packages_count'];
        }

        return $this->metadataSummary($summary);
    }
}
