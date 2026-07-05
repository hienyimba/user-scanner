<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BandcampValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'bandcamp';
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
        return 'Bandcamp';
    }

    public function siteUrl(): string
    {
        return 'https://bandcamp.com/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://bandcamp.com/{$target}";
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
        return [
            // No connector-specific headers inferred.
        ];
    }
    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();

        // Python parity: bandcamp uses explicit status/body markers (no generic anti-bot shortcut).
        if ($status === 200 && str_contains($body, ' collection | Bandcamp</title>')) {
            return ['Taken', ''];
        }

        if (
            $status === 404
            || str_contains($body, "<h2>Sorry, that something isnâ€™t here.</h2>")
            || str_contains($body, "<h2>Sorry, that something isn't here.</h2>")
            || str_contains($body, "<h2>Sorry, that something isn’t here.</h2>")
            || str_contains($body, "<h2>Sorry, that something isn???t here.</h2>")
        ) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
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
        if (preg_match('/data-blob=\"([^\"]+)\"/i', $html, $matches) !== 1) {
            return [];
        }

        $decoded = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        $blob = json_decode($decoded, true);
        if (!is_array($blob) || !is_array($blob['fan_data'] ?? null)) {
            return [];
        }

        $fan = $blob['fan_data'];
        $metadata = [
            'username' => $target,
            'sources' => ['html_hydration'],
        ];

        if (isset($fan['fan_id']) && is_numeric($fan['fan_id'])) {
            $metadata['bandcamp_id'] = (int) $fan['fan_id'];
        }

        $displayName = trim((string) ($fan['name'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $location = trim((string) ($fan['location'] ?? ''));
        if ($location !== '') {
            $metadata['location'] = $location;
        }

        $bio = trim((string) ($fan['bio'] ?? ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        $website = trim((string) ($fan['website_url'] ?? ''));
        if ($website !== '') {
            $metadata['website_url'] = $website;
            $metadata['external_links'] = [$website];
        }

        $followers = $fan['followers_count'] ?? null;
        if (is_numeric($followers)) {
            $metadata['followers'] = (int) $followers;
        }

        $followingBands = $fan['following_bands_count'] ?? null;
        if (is_numeric($followingBands)) {
            $metadata['following_bands'] = (int) $followingBands;
        }

        $followingFans = $fan['following_fans_count'] ?? null;
        if (is_numeric($followingFans)) {
            $metadata['following_fans'] = (int) $followingFans;
        }

        if (isset($metadata['following_bands']) || isset($metadata['following_fans'])) {
            $metadata['following'] = (int) (($metadata['following_bands'] ?? 0) + ($metadata['following_fans'] ?? 0));
        }

        $favGenre = trim((string) ($fan['fav_genre'] ?? ''));
        if ($favGenre !== '') {
            $metadata['fav_genre'] = $favGenre;
        }

        $imageId = data_get($fan, 'photo.image_id');
        if (is_numeric($imageId)) {
            $metadata['avatar_url'] = 'https://f4.bcbits.com/img/00' . (string) $imageId . '_10.jpg';
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
        if (isset($metadata['followers'])) {
            $summary['Followers'] = (string) $metadata['followers'];
        }
        if (is_string($metadata['fav_genre'] ?? null) && $metadata['fav_genre'] !== '') {
            $summary['Favorite Genre'] = $metadata['fav_genre'];
        }

        return $this->metadataSummary($summary);
    }
}
