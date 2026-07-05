<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/openstreetmap.py
// parity-class: generated

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class OpenstreetmapValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'openstreetmap';
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
        return 'Openstreetmap';
    }

    public function siteUrl(): string
    {
        return 'https://www.openstreetmap.org/user/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.openstreetmap.org/user/{$target}";
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
        $finalUrl = (string) ($response->effectiveUri() ?? '');

        if ($status === 404) {
            return ['Available', ''];
        }

        if (str_contains($body, 'Mapper since')) {
            return ['Taken', ''];
        }

        if (str_contains($body, 'does not exist')) {
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

        $body = $response->body();
        $metadata = [
            'username' => $target,
            'sources' => ['profile_html'],
        ];

        if (preg_match('/Mapper since:<\/dt>\s*<dd[^>]*>([^<]+)<\/dd>/', $body, $matches) === 1) {
            $joined = trim($matches[1]);
            if ($joined !== '') {
                try {
                    $metadata['created_at'] = (new \DateTimeImmutable($joined))
                        ->setTimezone(new \DateTimeZone('UTC'))
                        ->format(DATE_ATOM);
                } catch (\Throwable) {
                    $metadata['created_at'] = $joined;
                }
            }
        }

        if (preg_match('/class="user_image"[^>]*src="([^"]+)"/', $body, $matches) === 1) {
            $avatar = trim($matches[1]);
            if ($avatar !== '') {
                if (str_starts_with($avatar, '/')) {
                    $avatar = 'https://www.openstreetmap.org' . $avatar;
                }
                $metadata['avatar_url'] = $avatar;
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

        if (is_string($metadata['created_at'] ?? null) && $metadata['created_at'] !== '') {
            $summary['Joined'] = $metadata['created_at'];
        }
        if (is_string($metadata['avatar_url'] ?? null) && $metadata['avatar_url'] !== '') {
            $summary['Avatar'] = $metadata['avatar_url'];
        }

        return $this->metadataSummary($summary);
    }
}
