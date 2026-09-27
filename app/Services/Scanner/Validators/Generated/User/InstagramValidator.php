<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class InstagramValidator extends BaseGeneratedValidator
{
    private const WARMUP_URL = 'https://www.instagram.com/accounts/emailsignup/';

    private const SIGNUP_URL = 'https://www.instagram.com/api/v1/web/accounts/web_create_ajax/attempt/';

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

    public function check(string $target, array $options = []): ScanResult
    {
        $showUrl = 'https://www.instagram.com/'.rawurlencode($target).'/';

        if (strlen($target) < 1 || strlen($target) > 30 || preg_match('/^[a-zA-Z0-9._]+$/', $target) !== 1) {
            return $this->result($target, $showUrl, 'Error', 'Instagram usernames can only use 1-30 letters, numbers, underscores, and periods');
        }

        try {
            $cookies = new CookieJar;
            $client = Http::timeout(15)->withOptions([
                'allow_redirects' => false,
                'verify' => (bool) config('scanner.verify_ssl', false),
                'cookies' => $cookies,
            ])->withHeaders([
                'User-Agent' => (string) config('scanner.user_agent'),
                'Accept-Language' => 'en-US,en;q=0.9',
            ]);

            if (! empty($options['proxy'])) {
                $client = $client->withOptions(['proxy' => $options['proxy']]);
            }

            $client->get(self::WARMUP_URL);
            $profile = $client->get($showUrl);
            $metadata = $this->profileMetadata($profile, $target);
            if ($metadata !== null) {
                return $this->result($target, $showUrl, 'Taken', metadata: $metadata);
            }

            $csrf = $cookies->getCookieByName('csrftoken')?->getValue() ?? '';
            if ($csrf === '') {
                $client->get(self::WARMUP_URL);
                $csrf = $cookies->getCookieByName('csrftoken')?->getValue() ?? '';
            }

            $response = $client->asForm()->withHeaders([
                'Accept' => '*/*',
                'X-IG-App-ID' => '936619743392459',
                'X-CSRFToken' => $csrf,
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => self::WARMUP_URL,
            ])->post(self::SIGNUP_URL, [
                'username' => $target,
                'email' => '',
                'first_name' => '',
                'opt_into_one_tap' => 'false',
            ]);

            if (in_array($response->status(), [401, 403, 429], true)) {
                return $this->result($target, $showUrl, 'Error', 'Instagram blocked/rate-limited the signup fallback (HTTP '.$response->status().')');
            }
            if ($response->status() !== 200) {
                return $this->result($target, $showUrl, 'Error', 'Unexpected Instagram signup status: '.$response->status());
            }

            $data = $response->json();
            $usernameErrors = data_get($data, 'errors.username');
            if (is_array($usernameErrors)) {
                foreach ($usernameErrors as $error) {
                    $code = is_array($error) ? (string) ($error['code'] ?? '') : '';
                    if (in_array($code, ['username_invalid', 'username_is_taken', 'username_invalid_substring'], true)) {
                        return $this->result($target, $showUrl, 'Taken');
                    }
                    if (in_array($code, ['username_has_special_char', 'username_too_long'], true)) {
                        return $this->result($target, $showUrl, 'Error', (string) ($error['message'] ?? 'Invalid username'));
                    }
                }
            }

            if (($data['status'] ?? null) === 'ok' && empty($usernameErrors)) {
                return $this->result($target, $showUrl, 'Available');
            }

            return $this->result($target, $showUrl, 'Error', 'Instagram signup fallback gave no verdict');
        } catch (\Throwable $e) {
            return $this->result($target, $showUrl, 'Error', $e->getMessage());
        }
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Instagram uses a multi-step session flow'];
    }

    /** @return array<string, mixed>|null */
    private function profileMetadata(Response $response, string $target): ?array
    {
        if ($response->status() !== 200) {
            return null;
        }

        $html = $response->body();
        $title = $this->meta($html, 'og:title');
        if ($title === null || ! str_contains(strtolower($title), '(@'.strtolower($target).')')) {
            return null;
        }

        $metadata = [
            'username' => $target,
            'sources' => ['profile_html'],
        ];
        $displayName = trim((string) preg_replace('/\s*\(@[^)]+\).*$/u', '', $title));
        if ($displayName !== '' && strcasecmp($displayName, $target) !== 0) {
            $metadata['display_name'] = $displayName;
        }

        $description = $this->meta($html, 'og:description');
        if ($description !== null && preg_match('/([0-9.,]+[A-Za-z]?)\s*Followers,\s*([0-9.,]+[A-Za-z]?)\s*Following,\s*([0-9.,]+[A-Za-z]?)\s*Posts/i', $description, $stats) === 1) {
            $metadata['followers'] = $stats[1];
            $metadata['following'] = $stats[2];
            $metadata['posts_count'] = $stats[3];
        }

        $avatar = $this->meta($html, 'og:image');
        if ($avatar !== null && ! str_contains($avatar, 'static.cdninstagram.com/rsrc.php')) {
            $metadata['avatar_url'] = $avatar;
        }

        if (preg_match_all('/<script[^>]+type="application\/json"[^>]*>(.*?)<\/script>/is', $html, $scripts) > 0) {
            foreach ($scripts[1] as $script) {
                if (! str_contains($script, 'xig_user_by_username')) {
                    continue;
                }

                $decoded = json_decode(html_entity_decode($script, ENT_QUOTES | ENT_HTML5), true);
                $user = is_array($decoded) ? $this->findNestedKey($decoded, 'xig_user_by_username') : null;
                if (! is_array($user)) {
                    continue;
                }

                $metadata['sources'][] = 'embedded_json';
                foreach ([
                    'pk' => 'instagram_id',
                    'full_name' => 'display_name',
                    'biography' => 'bio',
                    'business_email' => 'public_email',
                    'fbid' => 'facebook_uid',
                ] as $source => $destination) {
                    if (($user[$source] ?? null) !== null && $user[$source] !== '') {
                        $metadata[$destination] = $user[$source];
                    }
                }
                foreach ([
                    'is_business_account' => 'is_business',
                    'is_joined_recently' => 'is_joined_recently',
                    'is_private' => 'is_private',
                    'is_verified' => 'is_verified',
                ] as $source => $destination) {
                    if (array_key_exists($source, $user)) {
                        $metadata[$destination] = (bool) $user[$source];
                    }
                }
                if (! empty($user['profile_pic_url'])) {
                    $metadata['avatar_url'] = (string) $user['profile_pic_url'];
                }
                if (is_numeric(data_get($user, 'edge_followed_by.count'))) {
                    $metadata['followers'] = (int) data_get($user, 'edge_followed_by.count');
                }
                if (is_numeric(data_get($user, 'edge_follow.count'))) {
                    $metadata['following'] = (int) data_get($user, 'edge_follow.count');
                }

                $externalLinks = [];
                if (! empty($user['external_url'])) {
                    $externalLinks[] = (string) $user['external_url'];
                    $metadata['website_url'] = (string) $user['external_url'];
                }
                foreach ((array) ($user['bio_links'] ?? []) as $link) {
                    if (is_array($link) && is_string($link['url'] ?? null) && $link['url'] !== '') {
                        $externalLinks[] = $link['url'];
                    }
                }
                if ($externalLinks !== []) {
                    $metadata['external_links'] = array_values(array_unique($externalLinks));
                }
                break;
            }
        }

        return $metadata;
    }

    private function findNestedKey(array $data, string $key): mixed
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        foreach ($data as $value) {
            if (! is_array($value)) {
                continue;
            }
            $found = $this->findNestedKey($value, $key);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function meta(string $html, string $property): ?string
    {
        if (preg_match('/property="'.preg_quote($property, '/').'" content="([^"]*)"/i', $html, $matches) !== 1) {
            return null;
        }

        $value = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));

        return $value !== '' ? $value : null;
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
