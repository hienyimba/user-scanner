<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class SoundcloudValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'soundcloud';
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
        return 'Soundcloud';
    }

    public function siteUrl(): string
    {
        return 'https://soundcloud.com/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://soundcloud.com/{$target}";
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

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();

        // Python parity: soundcloud has explicit status mapping and body markers.
        if ($status === 403) {
            return ['Error', '[403] Request forbidden try using proxy or VPN'];
        }

        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status === 200) {
            $text = $response->body();

            if (str_contains($text, "soundcloud://users:{$target}")) {
                return ['Taken', ''];
            }
            if (str_contains($text, '"username":"' . $target . '"')) {
                return ['Taken', ''];
            }
            if (str_contains($text, 'soundcloud://users:') && str_contains($text, '"username":"')) {
                return ['Taken', ''];
            }

            return ['Error', 'Unexpected response, report it via GitHub issues'];
        }

        return ['Error', 'Unknown Error report it via GitHub issues'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return [];
        }

        $metadata = parent::buildStructuredMetadata($response, $target, $status);
        $hydration = $this->extractHydrationUser($response);
        if ($hydration === null) {
            return $metadata;
        }

        $displayName = trim((string) ($hydration['full_name'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $username = trim((string) ($hydration['username'] ?? $target));
        if ($username !== '') {
            $metadata['username'] = $username;
        }

        $location = implode(', ', array_values(array_filter([
            trim((string) ($hydration['city'] ?? '')),
            trim((string) ($hydration['country_code'] ?? '')),
        ], static fn (string $value): bool => $value !== '')));
        if ($location !== '') {
            $metadata['location'] = $location;
        }

        $bio = trim((string) ($hydration['description'] ?? ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        $avatarUrl = trim((string) ($hydration['avatar_url'] ?? ''));
        if ($avatarUrl !== '') {
            $metadata['avatar_url'] = $avatarUrl;
        }

        if (isset($hydration['followers_count']) && is_numeric($hydration['followers_count'])) {
            $metadata['followers'] ??= (int) $hydration['followers_count'];
        }
        if (isset($hydration['followings_count']) && is_numeric($hydration['followings_count'])) {
            $metadata['following'] ??= (int) $hydration['followings_count'];
        }
        if (isset($hydration['track_count']) && is_numeric($hydration['track_count'])) {
            $metadata['posts_count'] ??= (int) $hydration['track_count'];
        }
        if (isset($hydration['playlist_count']) && is_numeric($hydration['playlist_count'])) {
            $metadata['playlist_count'] ??= (int) $hydration['playlist_count'];
        }
        if (isset($hydration['likes_count']) && is_numeric($hydration['likes_count'])) {
            $metadata['likes_count'] ??= (int) $hydration['likes_count'];
        }
        if (array_key_exists('verified', $hydration)) {
            $metadata['is_verified'] ??= (bool) $hydration['verified'];
        }
        if (isset($hydration['id']) && is_numeric($hydration['id'])) {
            $metadata['soundcloud_id'] ??= (int) $hydration['id'];
        }

        $metadata['sources'] = array_values(array_unique(array_merge(
            is_array($metadata['sources'] ?? null) ? $metadata['sources'] : [],
            ['profile_html', 'html_hydration']
        )));

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
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Bio'] = $metadata['bio'];
        }
        if (is_int($metadata['followers'] ?? null)) {
            $summary['Followers'] = $metadata['followers'];
        }
        if (is_int($metadata['following'] ?? null)) {
            $summary['Following'] = $metadata['following'];
        }
        if (is_int($metadata['posts_count'] ?? null)) {
            $summary['Tracks'] = $metadata['posts_count'];
        }
        if (is_int($metadata['playlist_count'] ?? null)) {
            $summary['Playlists'] = $metadata['playlist_count'];
        }
        if (is_int($metadata['likes_count'] ?? null)) {
            $summary['Likes'] = $metadata['likes_count'];
        }
        if (is_string($metadata['avatar_url'] ?? null) && $metadata['avatar_url'] !== '') {
            $summary['Avatar'] = $metadata['avatar_url'];
        }
        if (array_key_exists('is_verified', $metadata)) {
            $summary['Verified'] = $metadata['is_verified'] ? 'Yes' : 'No';
        }

        return $this->metadataSummary($summary);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractHydrationUser(Response $response): ?array
    {
        if (!preg_match('/window\.__sc_hydration\s*=\s*(.*?);/s', $response->body(), $matches)) {
            return null;
        }

        $data = json_decode($matches[1], true);
        if (!is_array($data)) {
            return null;
        }

        foreach ($data as $item) {
            if (!is_array($item) || ($item['hydratable'] ?? null) !== 'user' || !is_array($item['data'] ?? null)) {
                continue;
            }

            return $item['data'];
        }

        return null;
    }
}
