<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class RedditValidator extends BaseGeneratedValidator
{
    private const BASE_URL = 'https://www.reddit.com';

    private const AVAILABLE_URL = self::BASE_URL.'/api/username_available.json';

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
        return self::BASE_URL.'/user/{user}/';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $showUrl = self::BASE_URL.'/user/'.rawurlencode($target);

        try {
            $client = Http::timeout(15)->withOptions([
                'allow_redirects' => true,
                'verify' => (bool) config('scanner.verify_ssl', false),
                'cookies' => new CookieJar,
            ])->withHeaders([
                'User-Agent' => (string) config('scanner.user_agent'),
                'Accept-Language' => 'en-US,en;q=0.9',
            ]);
            if (! empty($options['proxy'])) {
                $client = $client->withOptions(['proxy' => $options['proxy']]);
            }

            $clearance = $client->get(self::BASE_URL.'/');
            if (str_contains($clearance->body(), 'Prove your humanity')) {
                $params = $this->challengeParams($clearance->body());
                if ($params === null) {
                    return $this->result($target, $showUrl, 'Error', 'Unsupported Reddit bot challenge');
                }
                $clearance = $client->get(self::BASE_URL.'/', $params);
            }
            if (in_array($clearance->status(), [401, 403, 429], true)) {
                return $this->result($target, $showUrl, 'Error', 'Reddit blocked/rate-limited the clearance request (HTTP '.$clearance->status().')');
            }

            $available = $this->availabilityVerdict($client, $target);
            if ($available === null) {
                return $this->result($target, $showUrl, 'Error', 'Reddit availability check gave no verdict');
            }
            if ($available) {
                return $this->result($target, $showUrl, 'Available');
            }

            $profile = $client->withHeaders(['Accept' => 'application/json'])->get(
                self::BASE_URL.'/user/'.rawurlencode($target).'/about.json'
            );
            $metadata = $this->profileMetadata($profile, $target);

            return $this->result($target, $showUrl, 'Taken', metadata: $metadata);
        } catch (\Throwable $e) {
            return $this->result($target, $showUrl, 'Error', $e->getMessage());
        }
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Reddit uses a multi-step clearance flow'];
    }

    /** @return array<string, string>|null */
    private function challengeParams(string $html): ?array
    {
        if (preg_match('/\(async e=>e\+e\)\("([0-9a-f]+)"\)/', $html, $seed) !== 1
            || preg_match('/name="token" value="([0-9a-f]+)"/', $html, $token) !== 1) {
            return null;
        }

        return [
            'solution' => $seed[1].$seed[1],
            'js_challenge' => '1',
            'token' => $token[1],
            'jsc_orig_r' => '',
        ];
    }

    private function availabilityVerdict(PendingRequest $client, string $target): ?bool
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $response = $client->withHeaders(['Accept' => 'application/json'])->get(self::AVAILABLE_URL, ['user' => $target]);
            if ($response->status() === 200) {
                $decoded = json_decode($response->body(), true);

                return is_bool($decoded) ? $decoded : null;
            }
            if (trim($response->body()) !== '') {
                return null;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function profileMetadata(Response $response, string $target): array
    {
        if ($response->status() === 404) {
            return ['username' => $target, 'status_detail' => 'deleted', 'sources' => ['availability_api']];
        }
        if ($response->status() !== 200 || ! is_array($response->json('data'))) {
            return ['username' => $target, 'sources' => ['availability_api']];
        }

        $user = $response->json('data');
        $metadata = [
            'username' => (string) ($user['name'] ?? $target),
            'status_detail' => ! empty($user['is_suspended']) ? 'suspended' : 'active',
            'sources' => ['availability_api', 'api_json'],
        ];

        if (! empty($user['icon_img'])) {
            $metadata['avatar_url'] = html_entity_decode((string) $user['icon_img'], ENT_QUOTES | ENT_HTML5);
        }
        if (! empty($user['created_utc']) && is_numeric($user['created_utc'])) {
            $metadata['created_at'] = gmdate(DATE_ATOM, (int) $user['created_utc']);
        }
        if (! empty(data_get($user, 'subreddit.public_description'))) {
            $metadata['bio'] = trim((string) data_get($user, 'subreddit.public_description'));
        }
        if (! empty(data_get($user, 'subreddit.title'))) {
            $metadata['display_name'] = trim((string) data_get($user, 'subreddit.title'));
        }
        if (is_numeric(data_get($user, 'subreddit.subscribers'))) {
            $metadata['followers'] = (int) data_get($user, 'subreddit.subscribers');
        }
        if (array_key_exists('verified', $user)) {
            $metadata['is_verified'] = (bool) $user['verified'];
        }
        foreach (['total_karma' => 'karma', 'link_karma' => 'link_karma', 'comment_karma' => 'comment_karma'] as $source => $destination) {
            if (is_numeric($user[$source] ?? null)) {
                $metadata[$destination] = (int) $user[$source];
            }
        }

        return $metadata;
    }

    /** @param array<string, mixed> $metadata */
    private function result(string $target, string $url, string $status, string $reason = '', array $metadata = []): ScanResult
    {
        return new ScanResult(
            $target,
            $this->category(),
            $this->siteName(),
            $url,
            $status,
            $reason,
            mode: $this->mode(),
            key: $this->key(),
            profileUrl: $status === 'Taken' ? $url : null,
            metadata: $metadata,
        );
    }
}
