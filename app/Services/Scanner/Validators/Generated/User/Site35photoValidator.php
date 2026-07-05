<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/35photo.py
// parity-class: generated

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class Site35photoValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return '35photo';
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
        return '35photo';
    }

    public function siteUrl(): string
    {
        return 'https://35photo.pro/@{user}/';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://35photo.pro/@{$target}/";
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

        if (str_contains($body, '<span title="Total photos')) {
            return ['Taken', ''];
        }

        if (str_contains($body, 'Catalogs of professional author') || $status === 302) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body'];
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

        if (preg_match('/<h1 class="thinFont">([^<]+)<\/h1>/i', $html, $matches) === 1) {
            $name = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));
            if ($name !== '') {
                $metadata['display_name'] = $name;
            }
        }

        $country = null;
        if (preg_match('/title="Photographers from ([^"]+)"/i', $html, $matches) === 1) {
            $country = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));
            if ($country !== '') {
                $metadata['country'] = $country;
            }
        }

        $city = null;
        if (preg_match('/title="Photographers from the city of\s*([^"]+)"/i', $html, $matches) === 1) {
            $city = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));
            if ($city !== '') {
                $metadata['city'] = $city;
            }
        }

        $locationParts = array_values(array_filter([$city, $country], static fn (?string $value): bool => $value !== null && $value !== ''));
        if ($locationParts !== []) {
            $metadata['location'] = implode(', ', $locationParts);
        }

        if (preg_match('/Total photos see.*?font-size:2\.6em">([0-9]+)<\/span>/is', $html, $matches) === 1) {
            $photos = (int) $matches[1];
            $metadata['posts_count'] = $photos;
            $metadata['photos'] = $photos;
        }

        if (preg_match('/img class="avatar140"\s+src="([^"]+)"/i', $html, $matches) === 1) {
            $avatar = trim($matches[1]);
            if ($avatar !== '') {
                $metadata['avatar_url'] = str_starts_with($avatar, '//') ? 'https:' . $avatar : $avatar;
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
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (isset($metadata['photos'])) {
            $summary['Photos'] = (string) $metadata['photos'];
        }

        return $this->metadataSummary($summary);
    }
}
