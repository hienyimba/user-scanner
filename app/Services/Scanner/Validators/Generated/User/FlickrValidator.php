<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/flickr.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class FlickrValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'flickr';
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
        return 'Flickr';
    }

    public function siteUrl(): string
    {
        return 'https://www.flickr.com/photos/{user}/';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.flickr.com/photos/{$target}/";
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

    if ($status >= 500) {
        if (str_contains($body, 'flickr_panda_error_pages') || str_contains($body, '<title>Flickr</title>')) {
            return ['Error', 'flickr: backend error page (HTTP ' . $status . ')'];
        }

        return ['Error', 'flickr: upstream error (HTTP ' . $status . ')'];
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

        $json = $this->extractModelExportJson($response->body());
        if ($json === null) {
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }

        $person = $this->findModel($data, 'person-models');
        $profile = $this->findModel($data, 'person-profile-models');
        $contacts = $this->findModel($data, 'person-contacts-count-models');

        $metadata = [
            'username' => $target,
            'sources' => ['html_hydration'],
        ];

        if (is_array($person)) {
            if (isset($person['id']) && is_numeric($person['id'])) {
                $metadata['flickr_id'] = (int) $person['id'];
            }

            $pathAlias = trim((string) ($person['pathAlias'] ?? ''));
            if ($pathAlias !== '') {
                $metadata['flickr_username'] = $pathAlias;
            }

            $nickname = trim((string) ($person['username'] ?? ''));
            if ($nickname !== '') {
                $metadata['flickr_nickname'] = $nickname;
            }

            $fullName = trim((string) ($person['realname'] ?? ''));
            if ($fullName !== '') {
                $metadata['display_name'] = $fullName;
            } elseif ($nickname !== '') {
                $metadata['display_name'] = $nickname;
            }

            $retina = data_get($person, 'buddyicon.data.retina');
            if (is_string($retina) && $retina !== '') {
                $metadata['avatar_url'] = str_starts_with($retina, 'https:') ? $retina : 'https:' . $retina;
            }

            if (array_key_exists('isPro', $person)) {
                $metadata['is_pro'] = filter_var($person['isPro'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $person['isPro'];
            }
        }

        if (is_array($profile)) {
            $location = trim((string) ($profile['location'] ?? ''));
            if ($location !== '') {
                $metadata['location'] = $location;
            }

            $photoCount = $profile['photoCount'] ?? null;
            if (is_numeric($photoCount)) {
                $metadata['posts_count'] = (int) $photoCount;
                $metadata['photos_count'] = (int) $photoCount;
            }
        }

        if (is_array($contacts)) {
            $followers = $contacts['followerCount'] ?? null;
            if (is_numeric($followers)) {
                $metadata['followers'] = (int) $followers;
            }

            $following = $contacts['followingCount'] ?? null;
            if (is_numeric($following)) {
                $metadata['following'] = (int) $following;
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
            $summary['Full Name'] = $metadata['display_name'];
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (isset($metadata['photos_count'])) {
            $summary['Photos'] = (string) $metadata['photos_count'];
        }
        if (isset($metadata['followers'])) {
            $summary['Followers'] = (string) $metadata['followers'];
        }

        return $this->metadataSummary($summary);
    }

    private function extractModelExportJson(string $html): ?string
    {
        if (preg_match('/modelExport:(.*),[\s\S]*auth/', $html, $matches) !== 1) {
            return null;
        }

        return str_replace(['%20', '%2C'], [' ', ','], $matches[1]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findModel(mixed $value, string $registry): ?array
    {
        if (is_array($value) && array_key_exists('_flickrModelRegistry', $value) && $value['_flickrModelRegistry'] === $registry) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $found = $this->findModel($item, $registry);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
