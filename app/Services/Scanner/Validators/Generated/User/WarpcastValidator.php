<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/warpcast.py
// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class WarpcastValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'warpcast';
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
        return 'Warpcast';
    }

    public function siteUrl(): string
    {
        return 'https://warpcast.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://client.warpcast.com/v2/user-by-username?username={$target}";
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
        if (in_array($response->status(), [400, 404], true)) {
            return ['Available', ''];
        }
        if ($response->status() === 200) {
            $data = $response->json();
            if (!empty(data_get($data, 'result.user'))) {
                return ['Taken', ''];
            }
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

        $user = $response->json('result.user');
        if (!is_array($user) || $user === []) {
            return [];
        }

        $metadata = [
            'username' => $target,
            'sources' => ['api_json'],
        ];

        if (isset($user['fid']) && is_numeric($user['fid'])) {
            $metadata['fid'] = (int) $user['fid'];
        }

        $displayName = trim((string) ($user['displayName'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $bio = trim((string) data_get($user, 'profile.bio.text', ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        $location = trim((string) data_get($user, 'profile.location.description', ''));
        if ($location !== '') {
            $metadata['location'] = $location;
        }

        $accountLevel = trim((string) ($user['accountLevel'] ?? ''));
        if ($accountLevel !== '') {
            $metadata['account_level'] = $accountLevel;
        }

        if (isset($user['followerCount']) && is_numeric($user['followerCount'])) {
            $metadata['followers'] = (int) $user['followerCount'];
        }
        if (isset($user['followingCount']) && is_numeric($user['followingCount'])) {
            $metadata['following'] = (int) $user['followingCount'];
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

        if (is_int($metadata['fid'] ?? null)) {
            $summary['FID'] = $metadata['fid'];
        }
        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['DisplayName'] = $metadata['display_name'];
        }
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Bio'] = $metadata['bio'];
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (is_string($metadata['account_level'] ?? null) && $metadata['account_level'] !== '') {
            $summary['AccountLevel'] = $metadata['account_level'];
        }
        if (is_int($metadata['followers'] ?? null)) {
            $summary['Followers'] = $metadata['followers'];
        }
        if (is_int($metadata['following'] ?? null)) {
            $summary['Following'] = $metadata['following'];
        }

        return $this->metadataSummary($summary);
    }
}
