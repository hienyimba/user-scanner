<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class XValidator extends BaseGeneratedValidator
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
        return 'X';
    }

    public function siteUrl(): string
    {
        return 'https://x.com/{user}';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $showUrl = 'https://x.com/' . $target;

        try {
            $vxResponse = $this->makeJsonRequest(
                url: 'https://api.vxtwitter.com/' . $target,
                headers: [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'application/json',
                ],
                options: $options,
            );

            if ($vxResponse->status() === 200) {
                $data = $vxResponse->json();
                if (is_array($data)) {
                    $metadata = [
                        'username' => $target,
                        'sources' => ['api_json'],
                    ];

                    $displayName = trim((string) ($data['name'] ?? ''));
                    if ($displayName !== '') {
                        $metadata['display_name'] = $displayName;
                    }
                    $bio = trim((string) ($data['description'] ?? ''));
                    if ($bio !== '') {
                        $metadata['bio'] = $bio;
                    }
                    $location = trim((string) ($data['location'] ?? ''));
                    if ($location !== '') {
                        $metadata['location'] = $location;
                    }
                    $created = trim((string) ($data['created_at'] ?? ''));
                    if ($created !== '') {
                        $timestamp = strtotime($created);
                        $metadata['created_at'] = $timestamp !== false
                            ? gmdate('Y-m-d\TH:i:s+00:00', $timestamp)
                            : $created;
                    }
                    if (isset($data['followers_count']) && is_numeric($data['followers_count'])) {
                        $metadata['followers'] = (int) $data['followers_count'];
                    }
                    if (isset($data['following_count']) && is_numeric($data['following_count'])) {
                        $metadata['following'] = (int) $data['following_count'];
                    }
                    $avatarUrl = trim((string) ($data['profile_image_url'] ?? ''));
                    if ($avatarUrl !== '') {
                        $metadata['avatar_url'] = str_replace('_normal', '', $avatarUrl);
                    }
                    if (array_key_exists('protected', $data)) {
                        $metadata['is_private'] = (bool) $data['protected'];
                    }
                    if (isset($data['tweet_count']) && is_numeric($data['tweet_count'])) {
                        $metadata['posts_count'] = (int) $data['tweet_count'];
                    }

                    return new ScanResult(
                        target: $target,
                        category: $this->category(),
                        siteName: $this->siteName(),
                        url: $this->siteUrl(),
                        status: 'Taken',
                        extra: $this->metadataSummary($this->summaryMetadata($metadata)),
                        mode: $this->mode(),
                        key: $this->key(),
                        profileUrl: $showUrl,
                        metadata: $metadata,
                    );
                }
            }
        } catch (\Throwable) {
            // Fall back to the availability endpoint below.
        }

        try {
            $response = $this->makeJsonRequest(
                url: 'https://api.twitter.com/i/users/username_available.json',
                headers: [
                    'Authority' => 'api.twitter.com',
                    'User-Agent' => (string) config('scanner.user_agent'),
                ],
                options: $options,
                query: [
                    'username' => $target,
                    'full_name' => 'John Doe',
                    'email' => 'johndoe07@gmail.com',
                ],
            );

            $status = $response->status();
            if (in_array($status, [401, 403, 429], true)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Twitter rate limit or blocked', mode: $this->mode(), key: $this->key(), profileUrl: $showUrl);
            }

            if ($status === 200) {
                $data = $response->json();
                if (($data['valid'] ?? null) === true) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Available', mode: $this->mode(), key: $this->key(), profileUrl: $showUrl);
                }
                if (($data['reason'] ?? null) === 'taken') {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Taken', mode: $this->mode(), key: $this->key(), profileUrl: $showUrl);
                }
                if (in_array($data['reason'] ?? null, ['improper_format', 'invalid_username'], true)) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'X says: ' . (string) ($data['desc'] ?? ''), mode: $this->mode(), key: $this->key(), profileUrl: $showUrl);
                }
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected status: ' . $status, mode: $this->mode(), key: $this->key(), profileUrl: $showUrl);
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key(), profileUrl: $showUrl);
        }
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
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
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function summaryMetadata(array $metadata): array
    {
        $summary = [];

        foreach ([
            'display_name' => 'Name',
            'bio' => 'Bio',
            'location' => 'Location',
            'created_at' => 'Created',
            'followers' => 'Followers',
            'following' => 'Following',
            'avatar_url' => 'Avatar',
            'posts_count' => 'Tweets',
        ] as $key => $label) {
            $value = $metadata[$key] ?? null;
            if ($value !== null && $value !== '' && $value !== []) {
                $summary[$label] = $value;
            }
        }

        if (array_key_exists('is_private', $metadata)) {
            $summary['Protected'] = $metadata['is_private'] ? 'Yes' : 'No';
        }

        return $summary;
    }
}
