<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/community/disqus.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class DisqusValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'disqus';
    }

    public function category(): string
    {
        return 'community';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Disqus';
    }

    public function siteUrl(): string
    {
        return 'https://disqus.com/by/{user}/';
    }

    protected function requestUrl(string $target): string
    {
        return "https://disqus.com/api/3.0/users/details?user=username%3A{$target}&attach=userFlaggedUser&api_key=E8Uh5l5fHZ6gD8U3KycjAIAk46f68Zw7C6eW8WSjZvCLXebZ7p0r1yrYDrLilk2F";
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
        if ($response->status() === 200) {
            $data = $response->json();
            $userData = data_get($data, 'response');
            if (is_array($userData) && array_key_exists('id', $userData)) {
                return ['Taken', ''];
            }
        }

        return ['Available', ''];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return [];
        }

        $user = data_get($response->json(), 'response');
        if (!is_array($user)) {
            return [];
        }

        $username = trim((string) ($user['username'] ?? ''));
        if ($username === '') {
            $username = $target;
        }

        $metadata = [
            'username' => $username,
            'sources' => ['api_json'],
        ];

        $displayName = trim((string) ($user['name'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $avatarUrl = trim((string) data_get($user, 'avatar.permalink', ''));
        if ($avatarUrl !== '') {
            $metadata['avatar_url'] = $avatarUrl;
        }

        $bio = trim((string) ($user['about'] ?? ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        $location = trim((string) ($user['location'] ?? ''));
        if ($location !== '') {
            $metadata['location'] = $location;
        }

        $websiteUrl = trim((string) ($user['url'] ?? ''));
        if ($websiteUrl !== '') {
            $metadata['website_url'] = $websiteUrl;
            $metadata['external_links'] = [$websiteUrl];
        }

        foreach ([
            'numFollowers' => 'followers',
            'numFollowing' => 'following',
            'numPosts' => 'posts_count',
        ] as $sourceKey => $metadataKey) {
            if (isset($user[$sourceKey]) && is_numeric($user[$sourceKey])) {
                $metadata[$metadataKey] = (int) $user[$sourceKey];
            }
        }

        $joinedAt = trim((string) ($user['joinedAt'] ?? ''));
        if ($joinedAt !== '') {
            try {
                $metadata['created_at'] = (new \DateTimeImmutable($joinedAt))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            } catch (\Throwable) {
                $metadata['created_at'] = $joinedAt;
            }
        }

        if (array_key_exists('isVerified', $user)) {
            $metadata['is_verified'] = (bool) $user['isVerified'];
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
        if (isset($metadata['following'])) {
            $summary['Following'] = (string) $metadata['following'];
        }
        if (isset($metadata['posts_count'])) {
            $summary['Posts'] = (string) $metadata['posts_count'];
        }

        return $this->metadataSummary($summary);
    }
}
