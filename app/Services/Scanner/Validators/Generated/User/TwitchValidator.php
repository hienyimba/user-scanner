<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class TwitchValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'twitch';
    }

    public function category(): string
    {
        return 'creator';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Twitch';
    }

    public function siteUrl(): string
    {
        return 'https://twitch.tv/{user}';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://gql.twitch.tv/gql';
    }

    protected function followRedirects(): bool
    {
        return false;
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept-Encoding' => 'identity',
            'Content-Type' => 'application/json',
            'sec-ch-ua-platform' => '"Android"',
            'accept-language' => 'en-US',
            'client-id' => 'kimne78kx3ncx6brgo4mv6wki5h1ko',
            'client-version' => '7bb0442d-1175-4ab5-9d32-b1f370536cbf',
            'origin' => 'https://m.twitch.tv',
            'referer' => 'https://m.twitch.tv/',
        ];
    }

    protected function requestBody(string $target): array
    {
        return [[
            'operationName' => 'ChannelLayout',
            'variables' => ['channelLogin' => $target, 'includeIsDJ' => true],
            'extensions' => ['persistedQuery' => ['version' => 1, 'sha256Hash' => '4c361fa1874dc8f6a49e62b56aa1032eccb31311bdb653918a924f96a8b2d1a6']],
        ]];
    }

    public function check(string $target, array $options = []): ScanResult
    {
        if (strlen($target) < 4 || strlen($target) > 25) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Username must be between 4 and 25 characters long', mode: $this->mode(), key: $this->key());
        }
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $target)) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Username can only contain letters, numbers, and underscores', mode: $this->mode(), key: $this->key());
        }

        $officialResult = parent::check($target, $options);
        if ($officialResult->status !== 'Error') {
            return $officialResult;
        }

        return $this->checkTwitchTracker($target, $options, $officialResult->reason);
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() !== 200) {
            return ['Error', 'Unexpected status code: '.$response->status()];
        }
        try {
            $data = $response->json();
        } catch (\Throwable $e) {
            return ['Error', 'Failed to decode JSON response: '.$e->getMessage()];
        }
        $userData = $data[0]['data']['user'] ?? [];
        $typename = $userData['__typename'] ?? null;

        return match ($typename) {
            'User' => ['Taken', ''],
            'UserDoesNotExist' => ['Available', ''],
            default => ['Error', 'Unexpected GraphQL response structure or type.'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if (! in_array($status, ['Taken', 'Found'], true)) {
            return [];
        }

        $user = data_get($response->json(), '0.data.user');
        if (! is_array($user) || ($user['__typename'] ?? null) !== 'User') {
            return [];
        }

        $metadata = [
            'username' => $target,
            'sources' => ['api_json'],
        ];

        if (isset($user['id']) && is_numeric($user['id'])) {
            $metadata['twitch_id'] = (int) $user['id'];
        } elseif (isset($user['id'])) {
            $metadata['twitch_id'] = (string) $user['id'];
        }

        $description = trim((string) ($user['description'] ?? ''));
        if ($description !== '') {
            $metadata['bio'] = $description;
        }

        $followers = data_get($user, 'followers.totalCount');
        if (is_numeric($followers)) {
            $metadata['followers'] = (int) $followers;
        }

        $externalLinks = [];
        $socialMedias = data_get($user, 'channel.socialMedias', []);
        if (is_array($socialMedias)) {
            foreach ($socialMedias as $socialMedia) {
                if (! is_array($socialMedia)) {
                    continue;
                }

                $name = trim((string) ($socialMedia['name'] ?? ''));
                $url = trim((string) ($socialMedia['url'] ?? ''));
                if ($name === '' || $url === '') {
                    continue;
                }

                $key = preg_replace('/[^a-z0-9]+/', '_', strtolower($name)) ?? '';
                if ($key !== '') {
                    $metadata[$key] = $url;
                }
                $externalLinks[] = $url;
            }
        }

        if ($externalLinks !== []) {
            $metadata['external_links'] = array_values(array_unique($externalLinks));
        }

        return $metadata;
    }

    protected function buildExtraMetadata(Response $response, string $target, string $status): string
    {
        if (! in_array($status, ['Taken', 'Found'], true)) {
            return '';
        }

        $metadata = $this->buildStructuredMetadata($response, $target, $status);
        $summary = [];

        if (isset($metadata['twitch_id'])) {
            $summary['ID'] = (string) $metadata['twitch_id'];
        }
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Description'] = $metadata['bio'];
        }
        if (isset($metadata['followers'])) {
            $summary['Followers'] = (string) $metadata['followers'];
        }
        if (($metadata['external_links'] ?? []) !== []) {
            $summary['Links'] = implode(', ', $metadata['external_links']);
        }

        return $this->metadataSummary($summary);
    }

    /**
     * TwitchTracker is deliberately an error fallback, not the primary source.
     * A generic HTTP 200 is never enough: the page must contain both its
     * profile-specific title and the requested channel identity.
     *
     * @param  array<string, mixed>  $options
     */
    private function checkTwitchTracker(string $target, array $options, string $officialReason): ScanResult
    {
        $trackerUrl = 'https://twitchtracker.com/'.rawurlencode($target);
        $showUrl = 'https://twitch.tv/'.rawurlencode($target);

        try {
            $request = Http::timeout(15)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->withHeaders([
                    'User-Agent' => (string) config('scanner.user_agent'),
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ]);

            if (! empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->get($trackerUrl);
            $body = $response->body();
            $title = $this->pageTitle($body);

            if ($response->status() === 404 && str_starts_with($title, '404 Page Not Found')) {
                return $this->trackerResult($target, $showUrl, 'Available');
            }

            if (in_array($response->status(), [401, 403, 429], true)) {
                return $this->trackerResult(
                    $target,
                    $showUrl,
                    'Error',
                    'Twitch GraphQL failed ('.$officialReason.'); TwitchTracker was blocked (HTTP '.$response->status().')',
                );
            }

            $identityPattern = '/name:\s*[\'\"]'.preg_quote($target, '/').'[\'\"]/i';
            if ($response->status() === 200
                && str_contains($title, ' - Streamer Overview & Stats')
                && str_ends_with($title, '· TwitchTracker')
                && preg_match($identityPattern, $body) === 1) {
                return $this->trackerResult(
                    $target,
                    $showUrl,
                    'Taken',
                    metadata: $this->trackerMetadata($body, $target, $title),
                );
            }

            return $this->trackerResult(
                $target,
                $showUrl,
                'Error',
                'Twitch GraphQL failed ('.$officialReason.'); TwitchTracker returned no identity-bound verdict',
            );
        } catch (\Throwable $e) {
            return $this->trackerResult(
                $target,
                $showUrl,
                'Error',
                'Twitch GraphQL failed ('.$officialReason.'); TwitchTracker request failed: '.$e->getMessage(),
            );
        }
    }

    /** @return array<string, mixed> */
    private function trackerMetadata(string $html, string $target, string $title): array
    {
        $metadata = [
            'username' => $target,
            'lookup_source' => 'twitchtracker',
            'sources' => ['third_party_profile_html'],
        ];

        $displayName = trim(explode(' - Streamer Overview', $title, 2)[0]);
        if ($displayName !== '' && strcasecmp($displayName, $target) !== 0) {
            $metadata['display_name'] = $displayName;
        }

        if (preg_match('/#([0-9,]+)\s*<\/div>\s*<div[^>]*>\s*RANK/i', $html, $rank) === 1) {
            $metadata['rank'] = (int) str_replace(',', '', $rank[1]);
        }
        if (preg_match('/([0-9,]+)\s*<\/div>\s*<div[^>]*>\s*FOLLOWERS/i', $html, $followers) === 1) {
            $metadata['followers'] = (int) str_replace(',', '', $followers[1]);
        }
        if (preg_match('/<img[^>]+src="([^"]+static-cdn\.jtvnw\.net\/jtv_user_pictures\/[^"]+)"/i', $html, $avatar) === 1) {
            $metadata['avatar_url'] = html_entity_decode($avatar[1], ENT_QUOTES | ENT_HTML5);
        }

        return $metadata;
    }

    private function pageTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) !== 1) {
            return '';
        }

        return trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5));
    }

    /** @param array<string, mixed> $metadata */
    private function trackerResult(
        string $target,
        string $url,
        string $status,
        string $reason = '',
        array $metadata = [],
    ): ScanResult {
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
