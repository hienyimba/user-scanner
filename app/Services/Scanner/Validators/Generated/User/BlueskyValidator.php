<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class BlueskyValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'bluesky';
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
        return 'Bluesky';
    }

    public function siteUrl(): string
    {
        return 'https://bsky.app/profile/{user}.bsky.social';
    }

    protected function timeoutSeconds(): int
    {
        return 15;
    }

    protected function requestUrl(string $target): string
    {
        return 'https://bsky.social/xrpc/com.atproto.temp.checkHandleAvailability';
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept-Encoding' => 'gzip',
            'atproto-accept-labelers' => 'did:plc:ar7c4by46qjdydhdevvrndac;redact',
            'sec-ch-ua-platform' => '"Android"',
            'sec-ch-ua' => '"Google Chrome";v="141", "Not?A_Brand";v="8", "Chromium";v="141"',
            'sec-ch-ua-mobile' => '?1',
            'origin' => 'https://bsky.app',
            'sec-fetch-site' => 'cross-site',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-dest' => 'empty',
            'referer' => 'https://bsky.app/',
            'accept-language' => 'en-US,en;q=0.9',
        ];
    }

    protected function requestQuery(string $target): array
    {
        return [
            'handle' => str_ends_with($target, '.bsky.social') ? $target : $target . '.bsky.social',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() === 200) {
            $data = $response->json();
            $resultType = $data['result']['$type'] ?? null;

            if ($resultType === 'com.atproto.temp.checkHandleAvailability#resultAvailable') {
                return ['Available', ''];
            }

            if ($resultType === 'com.atproto.temp.checkHandleAvailability#resultUnavailable') {
                return ['Taken', ''];
            }
        }

        if ($response->status() === 400) {
            return ['Error', 'Username can only contain letters, numbers, hyphens (no leading/trailing)'];
        }

        return ['Error', 'Invalid status code!'];
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $handle = str_ends_with($target, '.bsky.social') ? $target : $target . '.bsky.social';

        try {
            $response = $this->makeRequest($target, $options);
            [$status, $reason] = $this->parseConnectorResponse($response, $target);

            if ($status === 'Available' || $status === 'Error') {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    $status,
                    $reason,
                    mode: $this->mode(),
                    key: $this->key(),
                );
            }

            $profileResponse = $this->fetchProfileResponse($handle, $options);
            $metadata = $this->buildProfileMetadata($profileResponse, $target, $handle);
            $summary = [];

            if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
                $summary['Display Name'] = $metadata['display_name'];
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
            if (isset($metadata['posts_count'])) {
                $summary['Posts'] = (string) $metadata['posts_count'];
            }

            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Taken',
                '',
                $this->metadataSummary($summary),
                mode: $this->mode(),
                key: $this->key(),
                metadata: $metadata,
            );
        } catch (\Throwable $e) {
            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Error',
                $e->getMessage(),
                mode: $this->mode(),
                key: $this->key(),
            );
        }
    }

    private function fetchProfileResponse(string $handle, array $options): Response
    {
        $request = Http::timeout(5)
            ->withOptions([
                'allow_redirects' => true,
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])
            ->withHeaders([
                'User-Agent' => config('scanner.user_agent'),
                'Accept' => 'application/json, text/plain, */*',
            ]);

        if (!empty($options['proxy']) && is_string($options['proxy'])) {
            $request = $request->withOptions(['proxy' => $options['proxy']]);
        }

        return $request->get('https://public.api.bsky.app/xrpc/app.bsky.actor.getProfile', [
            'actor' => $handle,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProfileMetadata(Response $response, string $target, string $handle): array
    {
        $data = $response->json();
        if (!is_array($data)) {
            return [
                'username' => $handle,
                'sources' => ['api_json'],
            ];
        }

        $metadata = [
            'username' => $target,
            'handle' => trim((string) ($data['handle'] ?? $handle)),
            'sources' => ['api_json'],
        ];

        $displayName = trim((string) ($data['displayName'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $bio = trim((string) ($data['description'] ?? ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        $followers = $data['followersCount'] ?? null;
        if (is_numeric($followers)) {
            $metadata['followers'] = (int) $followers;
        }

        $following = $data['followsCount'] ?? null;
        if (is_numeric($following)) {
            $metadata['following'] = (int) $following;
        }

        $posts = $data['postsCount'] ?? null;
        if (is_numeric($posts)) {
            $metadata['posts_count'] = (int) $posts;
        }

        $avatar = trim((string) ($data['avatar'] ?? ''));
        if ($avatar !== '') {
            $metadata['avatar_url'] = $avatar;
        }

        return $metadata;
    }
}
