<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/music/mixcloud.py
// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class MixcloudValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'mixcloud';
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
        return 'Mixcloud';
    }

    public function siteUrl(): string
    {
        return 'https://www.mixcloud.com/{user}/';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.mixcloud.com/{$target}/";
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
            $data = $response->json();
            if (is_array($data) && array_key_exists('error', $data)) {
                return ['Available', ''];
            }

            return ['Taken', ''];
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

        $data = $response->json();
        if (!is_array($data)) {
            return [];
        }

        $metadata = [
            'username' => $target,
            'sources' => ['api_json'],
        ];

        $displayName = trim((string) ($data['name'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        if (isset($data['follower_count']) && is_numeric($data['follower_count'])) {
            $metadata['followers'] = (int) $data['follower_count'];
        }
        if (isset($data['following_count']) && is_numeric($data['following_count'])) {
            $metadata['following'] = (int) $data['following_count'];
        }

        $pictures = $data['pictures'] ?? null;
        if (is_array($pictures)) {
            foreach (['large', 'thumbnail', 'small'] as $key) {
                $avatarUrl = trim((string) ($pictures[$key] ?? ''));
                if ($avatarUrl !== '') {
                    $metadata['avatar_url'] = $avatarUrl;
                    break;
                }
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
        if (is_int($metadata['followers'] ?? null)) {
            $summary['Followers'] = $metadata['followers'];
        }
        if (is_int($metadata['following'] ?? null)) {
            $summary['Following'] = $metadata['following'];
        }
        if (is_string($metadata['avatar_url'] ?? null) && $metadata['avatar_url'] !== '') {
            $summary['Avatar'] = $metadata['avatar_url'];
        }

        return $this->metadataSummary($summary);
    }
}
