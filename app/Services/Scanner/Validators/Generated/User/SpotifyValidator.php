<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/music/spotify.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class SpotifyValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'spotify';
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
        return 'Spotify';
    }

    public function siteUrl(): string
    {
        return 'https://open.spotify.com/user/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.stats.fm/api/v1/users/{$target}";
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
    $body = $response->body();

    if ($status === 404) {
        return ['Available', ''];
    }

    if ($status === 200) {
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

        $item = data_get($response->json(), 'item');
        if (!is_array($item)) {
            return [];
        }

        $metadata = [
            'username' => $target,
            'sources' => ['api_json'],
        ];

        $displayName = trim((string) ($item['displayName'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $image = trim((string) ($item['image'] ?? ''));
        if ($image !== '') {
            $metadata['avatar_url'] = $image;
        }

        $createdAt = trim((string) ($item['createdAt'] ?? ''));
        if ($createdAt !== '') {
            try {
                $metadata['created_at'] = (new \DateTimeImmutable($createdAt))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            } catch (\Throwable) {
                $metadata['created_at'] = $createdAt;
            }
        }

        $timezone = trim((string) ($item['timezone'] ?? ''));
        if ($timezone !== '') {
            $metadata['timezone'] = $timezone;
        }

        $profile = $item['profile'] ?? null;
        if (is_array($profile)) {
            $bio = trim((string) ($profile['bio'] ?? ''));
            if ($bio !== '') {
                $metadata['bio'] = $bio;
            }

            $pronouns = trim((string) ($profile['pronouns'] ?? ''));
            if ($pronouns !== '') {
                $metadata['pronouns'] = $pronouns;
            }
        }

        if (array_key_exists('isPro', $item)) {
            $metadata['is_pro'] = (bool) $item['isPro'];
        }
        if (array_key_exists('isPlus', $item)) {
            $metadata['is_plus'] = (bool) $item['isPlus'];
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
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Bio'] = $metadata['bio'];
        }
        if (is_string($metadata['pronouns'] ?? null) && $metadata['pronouns'] !== '') {
            $summary['Pronouns'] = $metadata['pronouns'];
        }
        if (is_string($metadata['timezone'] ?? null) && $metadata['timezone'] !== '') {
            $summary['Timezone'] = $metadata['timezone'];
        }
        if (array_key_exists('is_pro', $metadata)) {
            $summary['Pro'] = $metadata['is_pro'] ? 'Yes' : 'No';
        }
        if (array_key_exists('is_plus', $metadata)) {
            $summary['Plus'] = $metadata['is_plus'] ? 'Yes' : 'No';
        }

        return $this->metadataSummary($summary);
    }
}
