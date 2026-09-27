<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class VenmoValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'venmo';
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
        return 'Venmo';
    }

    public function siteUrl(): string
    {
        return 'https://venmo.com/{user}';
    }

    public function publicProfileUrl(string $target): ?string
    {
        return 'https://venmo.com/'.rawurlencode($target);
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://api.venmo.com/v1/users/'.rawurlencode($target);
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $apiResult = parent::check($target, $options);
        if ($apiResult->status !== 'Error') {
            return $apiResult;
        }

        return $this->checkPublicProfileFallback($target, $options, $apiResult->reason);
    }

    /**
     * @return array{0:string,1:string}
     */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();

        if ($status === 404) {
            // Parity with venmo-osint: a JSON 404 means the user does not exist,
            // while an HTML 404 stub page means Venmo is throttling this IP.
            $contentType = strtolower((string) $response->header('Content-Type'));
            $body = ltrim($response->body());
            if (str_contains($contentType, 'html') || str_starts_with($body, '<')) {
                return ['Error', 'venmo: rate-limited (HTML 404 stub page)'];
            }

            return ['Available', ''];
        }

        if ($status === 400) {
            // The v1 users API answers HTTP 400 with code 283 ("Resource not found.")
            // for usernames that do not exist.
            $error = $response->json('error');
            $code = is_array($error) ? ($error['code'] ?? null) : null;
            $message = is_array($error) ? strtolower((string) ($error['message'] ?? '')) : '';
            if ($code === 283 || str_contains($message, 'resource not found')) {
                return ['Available', ''];
            }

            return ['Error', 'Unexpected status: 400'];
        }

        if ($status === 429) {
            return ['Error', 'venmo: rate-limited (HTTP 429)'];
        }

        if ($status !== 200) {
            return ['Error', 'Unexpected status: '.$status];
        }

        $user = $this->extractUserPayload($response, $target);
        if ($user !== null) {
            return ['Taken', ''];
        }

        return ['Error', 'Venmo returned no profile payload (possibly rate-limited or changed response shape)'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if (! in_array($status, ['Taken', 'Found'], true)) {
            return [];
        }

        $user = $this->extractUserPayload($response, $target);
        if ($user === null) {
            return [];
        }

        $metadata = [
            'username' => $this->stringValue($user['username'] ?? null) ?? $target,
            'sources' => ['api_json'],
        ];

        $userId = $user['id'] ?? $user['user_id'] ?? null;
        if (is_numeric($userId)) {
            $metadata['venmo_user_id'] = (int) $userId;
        } elseif ($this->stringValue($userId) !== null) {
            $metadata['venmo_user_id'] = $this->stringValue($userId);
        }

        $displayName = $this->displayName($user);
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }

        $bio = $this->stringValue($user['about'] ?? $user['description'] ?? $user['bio'] ?? null);
        if ($bio !== null) {
            $metadata['bio'] = $bio;
        }

        $avatarUrl = $this->stringValue($user['profile_picture_url'] ?? $user['profile_image_url'] ?? $user['avatar_url'] ?? null);
        if ($avatarUrl !== null) {
            $metadata['avatar_url'] = $avatarUrl;
        }

        $createdAt = $this->normalizeDateValue($user['date_joined'] ?? $user['created_at'] ?? null);
        if ($createdAt !== null) {
            $metadata['created_at'] = $createdAt;
        }

        $accountType = $this->stringValue($user['identity_type'] ?? $user['account_type'] ?? null);
        if ($accountType === null && array_key_exists('is_business', $user)) {
            $accountType = (bool) $user['is_business'] ? 'business' : 'personal';
        }
        if ($accountType !== null) {
            $metadata['account_type'] = $accountType;
        }

        if (array_key_exists('is_business', $user)) {
            $metadata['is_business'] = (bool) $user['is_business'];
        }

        $friendCount = $this->integerValue($user['friend_count'] ?? $user['friends_count'] ?? $user['friends'] ?? null);
        if ($friendCount !== null) {
            $metadata['friend_count'] = $friendCount;
        }

        $publicEmail = $this->stringValue($user['email'] ?? null);
        if ($publicEmail !== null && str_contains($publicEmail, '@')) {
            $metadata['public_email'] = $publicEmail;
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

        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Bio'] = $metadata['bio'];
        }
        if (isset($metadata['venmo_user_id'])) {
            $summary['User ID'] = (string) $metadata['venmo_user_id'];
        }
        if (is_string($metadata['account_type'] ?? null) && $metadata['account_type'] !== '') {
            $summary['Account Type'] = $metadata['account_type'];
        }
        if (isset($metadata['friend_count'])) {
            $summary['Friend Count'] = (string) $metadata['friend_count'];
        }
        if (is_string($metadata['created_at'] ?? null) && $metadata['created_at'] !== '') {
            $summary['Member Since'] = $metadata['created_at'];
        }
        if (is_string($metadata['avatar_url'] ?? null) && $metadata['avatar_url'] !== '') {
            $summary['Avatar'] = $metadata['avatar_url'];
        }

        return $this->metadataSummary($summary);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractUserPayload(Response $response, string $target): ?array
    {
        $decoded = $response->json();
        if (! is_array($decoded)) {
            return null;
        }

        $fallback = null;
        foreach ([
            $decoded['data']['user'] ?? null,
            $decoded['data'] ?? null,
            $decoded['user'] ?? null,
            $decoded,
        ] as $candidate) {
            $user = $this->normalizeUserCandidate($candidate);
            if ($user === null) {
                continue;
            }

            $fallback ??= $user;
            if ($this->matchesTarget($user, $target)) {
                return $user;
            }
        }

        return $fallback;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeUserCandidate(mixed $candidate): ?array
    {
        if (! is_array($candidate)) {
            return null;
        }

        if (array_is_list($candidate)) {
            foreach ($candidate as $item) {
                if (is_array($item)) {
                    return $item;
                }
            }

            return null;
        }

        if (is_array($candidate['user'] ?? null)) {
            return $this->normalizeUserCandidate($candidate['user']);
        }

        return $this->looksLikeUserCandidate($candidate) ? $candidate : null;
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function matchesTarget(array $user, string $target): bool
    {
        $username = $this->stringValue($user['username'] ?? null);
        if ($username !== null) {
            return strtolower($username) === strtolower($target);
        }

        return array_key_exists('id', $user) || array_key_exists('user_id', $user);
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function looksLikeUserCandidate(array $candidate): bool
    {
        foreach ([
            'id',
            'user_id',
            'username',
            'display_name',
            'first_name',
            'last_name',
            'profile_picture_url',
            'about',
            'description',
        ] as $key) {
            if (array_key_exists($key, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function displayName(array $user): ?string
    {
        foreach ([
            $user['display_name'] ?? null,
            $user['name'] ?? null,
            trim(implode(' ', array_filter([
                $this->stringValue($user['first_name'] ?? null),
                $this->stringValue($user['last_name'] ?? null),
            ]))),
        ] as $candidate) {
            $displayName = $this->stringValue($candidate);
            if ($displayName !== null) {
                return $displayName;
            }
        }

        return null;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }

    private function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return null;
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        $string = $this->stringValue($value);
        if ($string === null) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($string))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format(DATE_ATOM);
        } catch (\Throwable) {
            return $string;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function checkPublicProfileFallback(string $target, array $options, string $apiReason): ScanResult
    {
        $showUrl = $this->publicProfileUrl($target) ?? $this->siteUrl();
        $url = 'https://account.venmo.com/u/'.rawurlencode($target);

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

            $response = $request->get($url);
            $body = $response->body();
            $ogTitle = $this->metaContent($body, 'property', 'og:title');
            $ogUrl = $this->metaContent($body, 'property', 'og:url');
            $robots = strtolower((string) $this->metaContent($body, 'name', 'robots'));

            if ($response->status() === 200
                && $ogTitle !== null
                && $ogUrl !== null
                && $this->venmoProfileIdentityMatches($ogTitle, $ogUrl, $target)) {
                $metadata = [
                    'username' => $target,
                    'lookup_source' => 'public_profile_fallback',
                    'sources' => ['profile_html'],
                ];
                $displayName = trim(explode('|', $ogTitle, 2)[0]);
                if ($displayName !== '' && strcasecmp($displayName, $target) !== 0) {
                    $metadata['display_name'] = $displayName;
                }
                $avatar = $this->metaContent($body, 'property', 'og:image');
                if ($avatar !== null) {
                    $metadata['avatar_url'] = $avatar;
                }

                return $this->fallbackResult($target, $showUrl, 'Taken', metadata: $metadata);
            }

            if ($response->status() === 404
                && str_contains($robots, 'noindex')
                && $this->pageTitle($body) === 'Venmo') {
                return $this->fallbackResult($target, $showUrl, 'Available');
            }

            return $this->fallbackResult(
                $target,
                $showUrl,
                'Error',
                'Venmo API failed ('.$apiReason.'); public profile fallback returned no identity-bound verdict',
            );
        } catch (\Throwable $e) {
            return $this->fallbackResult(
                $target,
                $showUrl,
                'Error',
                'Venmo API failed ('.$apiReason.'); public profile fallback failed: '.$e->getMessage(),
            );
        }
    }

    private function venmoProfileIdentityMatches(string $title, string $url, string $target): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = rawurldecode(trim((string) parse_url($url, PHP_URL_PATH), '/'));

        return $host === 'account.venmo.com'
            && strcasecmp($path, 'u/'.$target) === 0
            && preg_match('/\|\s*@'.preg_quote($target, '/').'$/i', $title) === 1;
    }

    private function metaContent(string $html, string $attribute, string $expected): ?string
    {
        if (preg_match_all('/<meta\b[^>]*>/i', $html, $tags) < 1) {
            return null;
        }

        foreach ($tags[0] as $tag) {
            if (preg_match('/\b'.preg_quote($attribute, '/').'=["\']([^"\']*)["\']/i', $tag, $name) !== 1
                || strcasecmp($name[1], $expected) !== 0
                || preg_match('/\bcontent=["\']([^"\']*)["\']/i', $tag, $content) !== 1) {
                continue;
            }

            $value = trim(html_entity_decode($content[1], ENT_QUOTES | ENT_HTML5));

            return $value !== '' ? $value : null;
        }

        return null;
    }

    private function pageTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) !== 1) {
            return '';
        }

        return trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5));
    }

    /** @param array<string, mixed> $metadata */
    private function fallbackResult(
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
