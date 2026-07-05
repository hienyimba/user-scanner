<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\User;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class XValidator implements ValidatorContract
{
    public function key(): string
    {
        return 'x';
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
        return 'X (Twitter)';
    }

    public function siteUrl(): string
    {
        return 'https://x.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $showUrl = 'https://x.com/' . $target;

        try {
            $response = $this->makeJsonRequest(
                url: 'https://api.vxtwitter.com/' . $target,
                headers: [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                ],
                options: $options,
            );

            if ($response->status() === 200) {
                $payload = $response->json();
                if (is_array($payload)) {
                    $metadata = $this->buildProfileMetadata($target, $payload);

                    return new ScanResult(
                        target: $target,
                        category: $this->category(),
                        siteName: $this->siteName(),
                        url: $this->siteUrl(),
                        status: 'Taken',
                        extra: $this->summarizeMetadata($metadata),
                        mode: $this->mode(),
                        key: $this->key(),
                        profileUrl: $showUrl,
                        metadata: $metadata,
                    );
                }
            }
        } catch (\Throwable) {
            // Fall back to the legacy availability probe below.
        }

        try {
            $response = $this->makeJsonRequest(
                url: 'https://api.twitter.com/i/users/username_available.json',
                headers: [
                    'User-Agent' => (string) config('scanner.user_agent'),
                ],
                options: $options,
                query: [
                    'username' => $target,
                    'full_name' => 'John Doe',
                    'email' => 'placeholder@example.com',
                ],
            );

            if ($response->status() === 200 && $response->json('valid') === true) {
                return new ScanResult(
                    target: $target,
                    category: $this->category(),
                    siteName: $this->siteName(),
                    url: $this->siteUrl(),
                    status: 'Available',
                    mode: $this->mode(),
                    key: $this->key(),
                    profileUrl: $showUrl,
                );
            }

            if ($response->status() === 200 && $response->json('reason') === 'taken') {
                return new ScanResult(
                    target: $target,
                    category: $this->category(),
                    siteName: $this->siteName(),
                    url: $this->siteUrl(),
                    status: 'Taken',
                    mode: $this->mode(),
                    key: $this->key(),
                    profileUrl: $showUrl,
                );
            }

            $reason = in_array($response->status(), [401, 403, 429], true)
                ? 'Twitter rate limit or blocked'
                : 'Rate limited or blocked';

            return new ScanResult(
                target: $target,
                category: $this->category(),
                siteName: $this->siteName(),
                url: $this->siteUrl(),
                status: 'Error',
                reason: $reason,
                mode: $this->mode(),
                key: $this->key(),
                profileUrl: $showUrl,
            );
        } catch (\Throwable $e) {
            return new ScanResult(
                target: $target,
                category: $this->category(),
                siteName: $this->siteName(),
                url: $this->siteUrl(),
                status: 'Error',
                reason: $e->getMessage(),
                mode: $this->mode(),
                key: $this->key(),
                profileUrl: $showUrl,
            );
        }
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, string> $headers
     * @param array<string, scalar> $query
     */
    private function makeJsonRequest(string $url, array $headers, array $options, array $query = []): Response
    {
        $request = Http::timeout(10)
            ->withOptions([
                'allow_redirects' => true,
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])
            ->withHeaders($headers);

        if (!empty($options['proxy']) && is_string($options['proxy'])) {
            $request = $request->withOptions(['proxy' => $options['proxy']]);
        }

        return $request->get($url, $query);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function buildProfileMetadata(string $target, array $payload): array
    {
        $metadata = [
            'username' => $target,
            'sources' => ['api_json'],
        ];

        $displayName = trim((string) ($payload['name'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $bio = trim((string) ($payload['description'] ?? ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        $location = trim((string) ($payload['location'] ?? ''));
        if ($location !== '') {
            $metadata['location'] = $location;
        }

        $createdAt = trim((string) ($payload['created_at'] ?? ''));
        if ($createdAt !== '') {
            $timestamp = strtotime($createdAt);
            $metadata['created_at'] = $timestamp !== false
                ? gmdate('Y-m-d\TH:i:s+00:00', $timestamp)
                : $createdAt;
        }

        if (isset($payload['followers_count']) && is_numeric($payload['followers_count'])) {
            $metadata['followers'] = (int) $payload['followers_count'];
        }

        if (isset($payload['following_count']) && is_numeric($payload['following_count'])) {
            $metadata['following'] = (int) $payload['following_count'];
        }

        if (isset($payload['tweet_count']) && is_numeric($payload['tweet_count'])) {
            $metadata['posts_count'] = (int) $payload['tweet_count'];
        }

        $avatarUrl = trim((string) ($payload['profile_image_url'] ?? ''));
        if ($avatarUrl !== '') {
            $metadata['avatar_url'] = str_replace('_normal', '', $avatarUrl);
        }

        if (array_key_exists('protected', $payload)) {
            $metadata['is_private'] = (bool) $payload['protected'];
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function summarizeMetadata(array $metadata): string
    {
        $summary = [];

        foreach ([
            'display_name' => 'Name',
            'bio' => 'Bio',
            'location' => 'Location',
            'created_at' => 'Created',
            'followers' => 'Followers',
            'following' => 'Following',
            'posts_count' => 'Tweets',
            'avatar_url' => 'Avatar',
        ] as $key => $label) {
            $value = $metadata[$key] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $summary[] = $label . ': ' . $value;
        }

        if (array_key_exists('is_private', $metadata)) {
            $summary[] = 'Protected: ' . ($metadata['is_private'] ? 'Yes' : 'No');
        }

        return implode("\n", $summary);
    }
}
