<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class InstagramValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'instagram';
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
        return 'Instagram';
    }

    public function siteUrl(): string
    {
        return 'https://www.instagram.com/{user}/';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://www.instagram.com/api/v1/users/web_profile_info/';
    }

    protected function requestQuery(string $target): array
    {
        return [
            'username' => $target,
        ];
    }

    protected function requestHeadersForTarget(string $target): array
    {
        return [
            'Accept-Encoding' => 'gzip, deflate, br, zstd',
            'sec-ch-ua-full-version-list' => '"Not(A:Brand";v="8.0.0.0", "Chromium";v="144.0.7559.132", "Google Chrome";v="144.0.7559.132"',
            'sec-ch-ua-platform' => '"Linux"',
            'sec-ch-ua' => '"Not(A:Brand";v="8", "Chromium";v="144", "Google Chrome";v="144"',
            'sec-ch-ua-model' => '""',
            'sec-ch-ua-mobile' => '?0',
            'x-ig-app-id' => '936619743392459',
            'x-requested-with' => 'XMLHttpRequest',
            'sec-ch-prefers-color-scheme' => 'dark',
            'x-ig-www-claim' => '0',
            'sec-ch-ua-platform-version' => '""',
            'sec-fetch-site' => 'same-origin',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-dest' => 'empty',
            'referer' => "https://www.instagram.com/{$target}",
            'accept-language' => 'en-US,en;q=0.9',
            'priority' => 'u=1, i',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status === 200) {
            if (data_get($response->json(), 'data.user') !== null) {
                return ['Taken', ''];
            }

            return ['Available', ''];
        }

        return ['Error', $this->key() . ': blocked/rate-limited (HTTP ' . $status . ')'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return [];
        }

        $user = data_get($response->json(), 'data.user');
        if (!is_array($user)) {
            return [];
        }

        $metadata = [
            'username' => trim((string) ($user['username'] ?? '')) ?: $target,
            'sources' => ['api_json'],
        ];

        $fullName = trim((string) ($user['full_name'] ?? ''));
        if ($fullName !== '') {
            $metadata['display_name'] = $fullName;
        }

        if (isset($user['id'])) {
            $metadata['instagram_id'] = trim((string) $user['id']);
        }

        $avatar = trim((string) ($user['profile_pic_url_hd'] ?? ''));
        if ($avatar !== '') {
            $metadata['avatar_url'] = $avatar;
        }

        $bio = trim((string) ($user['biography'] ?? ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        $businessEmail = trim((string) ($user['business_email'] ?? ''));
        if ($businessEmail !== '') {
            $metadata['public_email'] = $businessEmail;
        }

        $externalUrl = trim((string) ($user['external_url'] ?? ''));
        if ($externalUrl !== '') {
            $metadata['website_url'] = $externalUrl;
            $metadata['external_links'] = [$externalUrl];
        }

        if (isset($user['fbid'])) {
            $metadata['facebook_uid'] = trim((string) $user['fbid']);
        }

        foreach ([
            'is_business_account' => 'is_business',
            'is_joined_recently' => 'is_joined_recently',
            'is_private' => 'is_private',
            'is_verified' => 'is_verified',
        ] as $sourceKey => $metadataKey) {
            if (array_key_exists($sourceKey, $user)) {
                $metadata[$metadataKey] = (bool) $user[$sourceKey];
            }
        }

        $followers = data_get($user, 'edge_followed_by.count');
        if (is_numeric($followers)) {
            $metadata['followers'] = (int) $followers;
        }

        $following = data_get($user, 'edge_follow.count');
        if (is_numeric($following)) {
            $metadata['following'] = (int) $following;
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
        if (isset($metadata['followers'])) {
            $summary['Followers'] = (string) $metadata['followers'];
        }
        if (isset($metadata['following'])) {
            $summary['Following'] = (string) $metadata['following'];
        }
        if (is_string($metadata['website_url'] ?? null) && $metadata['website_url'] !== '') {
            $summary['Website'] = $metadata['website_url'];
        }

        return $this->metadataSummary($summary);
    }
}
