<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class RedditValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'reddit';
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
        return 'Reddit';
    }

    public function siteUrl(): string
    {
        return 'https://www.reddit.com/user/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.reddit.com/user/{$target}/about.json";
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status === 429) {
            return ['Error', 'Rate limit exceeded'];
        }

        if ($status === 200) {
            try {
                $data = $response->json();
            } catch (\Throwable) {
                return ['Error', 'Malformed JSON response, report it on Github'];
            }

            if (($data['error'] ?? null) === 404 || ($data['message'] ?? null) === 'Not Found') {
                return ['Available', ''];
            }

            if (($data['kind'] ?? null) === 't2' || array_key_exists('data', $data)) {
                return ['Taken', ''];
            }
        }

        return ['Error', 'HTTP ' . $status];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return [];
        }

        $user = data_get($response->json(), 'data');
        if (!is_array($user)) {
            return [];
        }

        $username = trim((string) ($user['name'] ?? ''));
        if ($username === '') {
            $username = $target;
        }

        $subreddit = data_get($user, 'subreddit');
        $metadata = [
            'username' => $username,
            'sources' => ['api_json'],
        ];

        $displayName = trim((string) data_get($subreddit, 'title', ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $avatarUrl = trim((string) ($user['icon_img'] ?? ($user['snoovatar_img'] ?? data_get($subreddit, 'icon_img', ''))));
        if ($avatarUrl !== '') {
            $metadata['avatar_url'] = $avatarUrl;
        }

        $bio = trim((string) data_get($subreddit, 'public_description', ''));
        if ($bio === '') {
            $bio = trim((string) data_get($subreddit, 'description', ''));
        }
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        if (is_numeric(data_get($subreddit, 'subscribers'))) {
            $metadata['followers'] = (int) data_get($subreddit, 'subscribers');
        }

        if (isset($user['created_utc']) && is_numeric($user['created_utc'])) {
            try {
                $metadata['created_at'] = (new \DateTimeImmutable('@' . (string) $user['created_utc']))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            } catch (\Throwable) {
                // Ignore invalid upstream timestamps.
            }
        }

        if (array_key_exists('verified', $user)) {
            $metadata['is_verified'] = (bool) $user['verified'];
        }

        foreach ([
            'total_karma' => 'karma',
            'link_karma' => 'link_karma',
            'comment_karma' => 'comment_karma',
            'awarder_karma' => 'awarder_karma',
            'awardee_karma' => 'awardee_karma',
        ] as $sourceKey => $metadataKey) {
            if (isset($user[$sourceKey]) && is_numeric($user[$sourceKey])) {
                $metadata[$metadataKey] = (int) $user[$sourceKey];
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
        if (isset($metadata['followers'])) {
            $summary['Followers'] = (string) $metadata['followers'];
        }
        if (isset($metadata['karma'])) {
            $summary['Karma'] = (string) $metadata['karma'];
        }
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Bio'] = $metadata['bio'];
        }
        if (is_string($metadata['created_at'] ?? null) && $metadata['created_at'] !== '') {
            $summary['Created'] = $metadata['created_at'];
        }

        return $this->metadataSummary($summary);
    }
}
