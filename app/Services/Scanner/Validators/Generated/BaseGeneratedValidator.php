<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use App\Services\Scanner\ProfileMetadataExtractor;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class BaseGeneratedValidator implements ValidatorContract
{
    /** @var array<string, mixed> */
    private array $lastRequestDiagnostics = [];

    public function publicProfileUrl(string $target): ?string
    {
        $siteUrl = $this->resolveTemplateUrl($this->siteUrl(), $target);
        if ($siteUrl !== null) {
            return $siteUrl;
        }

        if ($this->mode() !== 'username' || strtoupper($this->requestMethod()) !== 'GET') {
            return null;
        }

        $requestUrl = $this->requestUrl($target);
        if (!$this->looksLikePublicProfileUrl($requestUrl, $target)) {
            return null;
        }

        return $requestUrl;
    }

    /**
     * @param array<string, mixed> $query
     * @return array{0:string,1:array<string, mixed>}
     */
    protected function normalizeUrlAndQuery(string $url, array $query): array
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['query'])) {
            return [$url, $query];
        }

        $inlineQuery = [];
        foreach (explode('&', $parts['query']) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$rawKey, $rawValue] = array_pad(explode('=', $pair, 2), 2, '');
            $inlineQuery[rawurldecode($rawKey)] = rawurldecode($rawValue);
        }
        $normalizedUrl = preg_replace('/\?.*$/', '', $url) ?? $url;

        return [$normalizedUrl, array_merge($inlineQuery, $query)];
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return $this->siteUrl();
    }

    /** @return array<string,string> */
    protected function requestHeaders(): array
    {
        return [];
    }

    /** @return array<string,string> */
    protected function requestHeadersForTarget(string $target): array
    {
        return $this->requestHeaders();
    }

    /** @return array<string,mixed> */
    protected function requestQuery(string $target): array
    {
        return [];
    }

    /** @return array<string,mixed> */
    protected function requestBody(string $target): array
    {
        return [];
    }

    protected function requestBodyMode(): string
    {
        return 'form';
    }

    protected function requestRawBody(string $target): ?string
    {
        return null;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    /**
     * @return array{0:string,1:string}
     */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }

    protected function makeRequest(string $target, array $options = []): Response
    {
        $startedAt = microtime(true);
        $request = Http::timeout($this->timeoutSeconds())
            ->withOptions([
                'allow_redirects' => $this->followRedirects(),
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])
            ->withHeaders(array_merge([
            'User-Agent' => config('scanner.user_agent'),
            'Accept' => 'text/html,application/json,*/*;q=0.8',
        ], $this->requestHeadersForTarget($target)));



        if (!empty($options['proxy'])) {
            $request = $request->withOptions(['proxy' => $options['proxy']]);
        }

        $method = strtoupper($this->requestMethod());
        [$url, $query] = $this->normalizeUrlAndQuery($this->requestUrl($target), $this->requestQuery($target));
        $body = $this->requestBody($target);
        $rawBody = $this->requestRawBody($target);
        $headers = array_change_key_case($this->requestHeadersForTarget($target), CASE_LOWER);
        $contentType = (string) ($headers['content-type'] ?? 'application/json');

        if ($query !== [] && $method !== 'GET') {
            $request = $request->withOptions(['query' => $query]);
        }

        if ($method === 'GET') {
            $response = $request->get($url, $query);
            $this->rememberRequestDiagnostics($response, $options['proxy'] ?? null, $startedAt);

            return $response;
        }

        if ($method === 'POST') {
            if ($rawBody !== null) {
                $response = $request->withBody($rawBody, $contentType)->post($url);
                $this->rememberRequestDiagnostics($response, $options['proxy'] ?? null, $startedAt);

                return $response;
            }

            if ($body !== []) {
                $response = match ($this->requestBodyMode()) {
                    'json' => $request->post($url, $body),
                    default => $request->asForm()->post($url, $body),
                };

                $this->rememberRequestDiagnostics($response, $options['proxy'] ?? null, $startedAt);

                return $response;
            }

            $response = $request->post($url);
            $this->rememberRequestDiagnostics($response, $options['proxy'] ?? null, $startedAt);

            return $response;
        }

        /** @var Response $response */
        $response = $request->send($method, $url, [
            'query' => $query,
            'form_params' => $body,
        ]);
        $this->rememberRequestDiagnostics($response, $options['proxy'] ?? null, $startedAt);

        return $response;
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $startedAt = microtime(true);
        $this->lastRequestDiagnostics = [];

        try {
            $response = $this->makeRequest($target, $options);
            $challenge = $this->detectBlockedOrChallenged($response);
            [$status, $reason] = $challenge ?? $this->parseConnectorResponse($response, $target);
            $structuredMetadata = $challenge === null ? $this->buildStructuredMetadata($response, $target, $status) : [];
            $structuredMetadata = $this->mergeRequestDiagnostics($structuredMetadata, $options, $response, $startedAt);
            $extra = $challenge === null ? $this->buildExtraMetadata($response, $target, $status) : '';

            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                $status,
                $reason,
                $extra,
                mode: $this->mode(),
                key: $this->key(),
                metadata: $structuredMetadata,
            );
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = match (true) {
                str_contains($message, 'timed out') => 'Request timeout',
                str_contains($message, 'ssl_read'),
                str_contains($message, 'unexpected eof while reading') => 'TLS/anti-bot layer closed the connection unexpectedly',
                default => $e->getMessage(),
            };

            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Error',
                $reason,
                mode: $this->mode(),
                key: $this->key(),
                metadata: $this->mergeRequestDiagnostics([], $options, null, $startedAt),
            );
        }
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function mergeRequestDiagnostics(array $metadata, array $options = [], ?Response $response = null, ?float $startedAt = null): array
    {
        return array_merge($metadata, $this->requestDiagnostics($options, $response, $startedAt));
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function requestDiagnostics(array $options = [], ?Response $response = null, ?float $startedAt = null): array
    {
        if ($response !== null) {
            return $this->buildRequestDiagnosticsPayload(
                $options['proxy'] ?? null,
                $response->status(),
                microtime(true) - ($startedAt ?? microtime(true)),
            );
        }

        if ($this->lastRequestDiagnostics !== []) {
            return $this->lastRequestDiagnostics;
        }

        if ($startedAt === null) {
            return [];
        }

        return $this->buildRequestDiagnosticsPayload(
            $options['proxy'] ?? null,
            null,
            microtime(true) - $startedAt,
        );
    }

    protected function looksLikeHtml(Response $response): bool
    {
        $contentType = strtolower((string) $response->header('Content-Type'));
        $body = ltrim($response->body());

        return str_contains($contentType, 'text/html')
            || str_starts_with($body, '<!doctype')
            || str_starts_with($body, '<html');
    }

    /**
     * @return array{0:string,1:string}|null
     */
    protected function detectBlockedOrChallenged(Response $response): ?array
    {
        $status = $response->status();
        if (in_array($status, [401, 403, 429], true)) {
            return ['Error', $this->key() . ': blocked/rate-limited (HTTP ' . $status . ')'];
        }

        if ($status === 404) {
            return null;
        }

        $effectiveUri = strtolower((string) ($response->effectiveUri() ?? ''));
        if ($effectiveUri !== '' && (str_contains($effectiveUri, '/verify-human/') || str_contains($effectiveUri, 'captcha'))) {
            return ['Error', $this->key() . ': anti-bot challenge detected'];
        }

        if (!$this->looksLikeHtml($response)) {
            return null;
        }

        $body = strtolower($response->body());
        foreach ([
            'verify you are human',
            'bot check',
            'security verification',
            'checking your browser',
            'just a moment',
            'access denied',
        ] as $needle) {
            if ($needle !== '' && str_contains($body, $needle)) {
                return ['Error', $this->key() . ': anti-bot challenge detected'];
            }
        }

        return null;
    }

    protected function buildExtraMetadata(Response $response, string $target, string $status): string
    {
        if (!in_array($status, ['Found', 'Registered'], true)) {
            return '';
        }

        return $this->metadataSummary($this->extractPositiveMetadata($response, $target, $status));
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if ($this->mode() !== 'username' || !in_array($status, ['Taken', 'Found'], true)) {
            if ($this->mode() === 'email' && in_array($status, ['Registered', 'Found'], true)) {
                return $this->extractStructuredEmailMetadata($response, $target);
            }

            return [];
        }

        $profileUrl = $this->publicProfileUrl($target);
        if ($profileUrl === null) {
            return [];
        }

        $jsonMetadata = $this->extractStructuredJsonMetadata($response, $profileUrl, $target);
        if ($jsonMetadata !== []) {
            return $jsonMetadata;
        }

        if (!$this->looksLikeHtml($response)) {
            return [];
        }

        /** @var ProfileMetadataExtractor $extractor */
        $extractor = app(ProfileMetadataExtractor::class);

        return $extractor->extractProfileHtmlMetadata(
            substr($response->body(), 0, (int) config('scanner.metadata.max_html_bytes', 262144)),
            $profileUrl,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractPositiveMetadata(Response $response, string $target, string $status): array
    {
        $data = $response->json();
        if (!is_array($data)) {
            return [];
        }

        $metadata = [];

        $userId = $data['user_id'] ?? null;
        if ($userId !== null && $userId !== '') {
            $metadata['User ID'] = (string) $userId;
        }

        $loginMethods = $this->collectScalarValues($data['loginMethods'] ?? null);
        if ($loginMethods !== []) {
            $metadata['Login methods'] = $loginMethods;
        }

        $accountsData = $data['accountsData'] ?? null;
        if (is_array($accountsData) && $accountsData !== []) {
            $metadata['Accounts matched'] = count($accountsData);

            $providers = [];
            $accountTypes = [];
            foreach ($accountsData as $account) {
                if (!is_array($account)) {
                    continue;
                }

                foreach (['provider', 'identityProvider', 'loginMethod'] as $key) {
                    $value = $account[$key] ?? null;
                    if (is_scalar($value) && trim((string) $value) !== '') {
                        $providers[] = trim((string) $value);
                    }
                }

                foreach (['accountType', 'type'] as $key) {
                    $value = $account[$key] ?? null;
                    if (is_scalar($value) && trim((string) $value) !== '') {
                        $accountTypes[] = trim((string) $value);
                    }
                }
            }

            if ($providers !== []) {
                $metadata['Providers'] = array_values(array_unique($providers));
            }
            if ($accountTypes !== []) {
                $metadata['Account types'] = array_values(array_unique($accountTypes));
            }
        }

        if (array_is_list($data)) {
            $methods = [];
            $accountTypes = [];

            foreach ($data as $account) {
                if (!is_array($account)) {
                    continue;
                }

                foreach ((array) ($account['authenticationMethods'] ?? []) as $method) {
                    if (!is_array($method)) {
                        continue;
                    }

                    $id = $method['id'] ?? null;
                    if (is_scalar($id) && trim((string) $id) !== '') {
                        $methods[] = trim((string) $id);
                    }
                }

                foreach (['accountType', 'type'] as $key) {
                    $value = $account[$key] ?? null;
                    if (is_scalar($value) && trim((string) $value) !== '') {
                        $accountTypes[] = trim((string) $value);
                    }
                }
            }

            if ($methods !== []) {
                $metadata['Authentication methods'] = array_values(array_unique($methods));
            }
            if ($accountTypes !== []) {
                $metadata['Account types'] = array_values(array_unique($accountTypes));
            }
        }

        return $metadata;
    }

    /**
     * @return array{0:string,1:string}
     */
    protected function parseDiscourseProfileResponse(Response $response, bool $emptyUserMeansAvailable = false): array
    {
        $status = $response->status();

        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status !== 200) {
            return ['Error', 'Unexpected response status: ' . $status];
        }

        $data = $response->json();
        $user = is_array($data) ? ($data['user'] ?? null) : null;
        if (is_array($user) && $user !== []) {
            return ['Taken', ''];
        }

        if ($emptyUserMeansAvailable) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response status: ' . $status];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractStructuredJsonMetadata(Response $response, string $profileUrl, string $target): array
    {
        $data = $response->json();
        if (!is_array($data)) {
            return [];
        }

        $user = $data['user'] ?? null;
        if (is_array($user) && array_key_exists('avatar_template', $user)) {
            return $this->extractDiscourseProfileMetadata($user, $profileUrl, $target);
        }

        $githubStyle = $this->extractGithubStyleProfileMetadata($data, $profileUrl, $target);
        if ($githubStyle !== []) {
            return $githubStyle;
        }

        $dailymotion = $this->extractDailymotionProfileMetadata($data, $profileUrl, $target);
        if ($dailymotion !== []) {
            return $dailymotion;
        }

        $codewars = $this->extractCodewarsProfileMetadata($data, $profileUrl, $target);
        if ($codewars !== []) {
            return $codewars;
        }

        if (is_array($user)) {
            $crates = $this->extractCratesProfileMetadata($user, $profileUrl, $target);
            if ($crates !== []) {
                return $crates;
            }
        }

        return $this->extractPx500ProfileMetadata($data, $profileUrl, $target);
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractStructuredEmailMetadata(Response $response, string $target): array
    {
        $data = $response->json();
        if (!is_array($data)) {
            return [];
        }

        $metadata = [
            'public_email' => $target,
            'sources' => ['api_json'],
        ];
        $hasSignal = false;

        $userId = $data['user_id'] ?? null;
        if ((is_scalar($userId) || (is_object($userId) && method_exists($userId, '__toString'))) && trim((string) $userId) !== '') {
            $metadata['user_id'] = is_numeric((string) $userId) ? (int) $userId : (string) $userId;
            $hasSignal = true;
        }

        $loginMethods = $this->collectScalarValues($data['loginMethods'] ?? null);
        if ($loginMethods !== []) {
            $metadata['login_methods'] = $loginMethods;
            $hasSignal = true;
        }

        $accountsData = $data['accountsData'] ?? null;
        if (is_array($accountsData) && $accountsData !== []) {
            $metadata['accounts_matched'] = count($accountsData);
            $hasSignal = true;

            $providers = [];
            $accountTypes = [];
            foreach ($accountsData as $account) {
                if (!is_array($account)) {
                    continue;
                }

                foreach (['provider', 'identityProvider', 'loginMethod'] as $key) {
                    $value = $account[$key] ?? null;
                    if (is_scalar($value) && trim((string) $value) !== '') {
                        $providers[] = trim((string) $value);
                    }
                }

                foreach (['accountType', 'type'] as $key) {
                    $value = $account[$key] ?? null;
                    if (is_scalar($value) && trim((string) $value) !== '') {
                        $accountTypes[] = trim((string) $value);
                    }
                }
            }

            $providers = array_values(array_unique($providers));
            $accountTypes = array_values(array_unique($accountTypes));

            if ($providers !== []) {
                $metadata['providers'] = $providers;
            }
            if ($accountTypes !== []) {
                $metadata['account_types'] = $accountTypes;
            }
        }

        if (array_is_list($data)) {
            $methods = [];
            $accountTypes = [];

            foreach ($data as $account) {
                if (!is_array($account)) {
                    continue;
                }

                foreach ((array) ($account['authenticationMethods'] ?? []) as $method) {
                    if (!is_array($method)) {
                        continue;
                    }

                    $id = $method['id'] ?? null;
                    if (is_scalar($id) && trim((string) $id) !== '') {
                        $methods[] = trim((string) $id);
                    }
                }

                foreach (['accountType', 'type'] as $key) {
                    $value = $account[$key] ?? null;
                    if (is_scalar($value) && trim((string) $value) !== '') {
                        $accountTypes[] = trim((string) $value);
                    }
                }
            }

            $methods = array_values(array_unique($methods));
            $accountTypes = array_values(array_unique($accountTypes));

            if ($methods !== []) {
                $metadata['authentication_methods'] = $methods;
                $hasSignal = true;
            }
            if ($accountTypes !== []) {
                $metadata['account_types'] = $accountTypes;
                $hasSignal = true;
            }
        }

        return $hasSignal ? $metadata : [];
    }

    protected function normalizeAbsoluteUrlValue(mixed $value, string $baseUrl): ?string
    {
        return $this->normalizeAbsoluteUrl($value, $baseUrl);
    }

    protected function normalizeDateMetadataValue(mixed $value): ?string
    {
        return $this->normalizeDateValue($value);
    }

    protected function normalizeIntegerMetadataValue(mixed $value): ?int
    {
        return $this->normalizeIntegerValue($value);
    }

    protected function normalizePublicEmailValue(mixed $value): ?string
    {
        return $this->normalizePublicEmail($value);
    }

    protected function nonEmptyStringValue(mixed $value): ?string
    {
        return $this->nonEmptyString($value);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    protected function extractDiscourseProfileMetadata(array $user, string $profileUrl, string $target): array
    {
        $username = $this->nonEmptyString($user['username'] ?? null) ?? $target;
        $displayName = $this->nonEmptyString($user['name'] ?? null) ?? $username;
        $avatarUrl = $this->resolveAvatarTemplateUrl($user['avatar_template'] ?? null, $profileUrl);
        $bio = $this->nonEmptyString($user['bio_excerpt'] ?? null)
            ?? $this->nonEmptyString($user['bio_raw'] ?? null);
        $location = $this->nonEmptyString($user['location'] ?? null);
        $websiteUrl = $this->normalizeAbsoluteUrl($user['website'] ?? null, $profileUrl);
        $createdAt = $this->normalizeDateValue($user['created_at'] ?? null);
        $lastActiveAt = $this->normalizeDateValue($user['last_seen_at'] ?? null)
            ?? $this->normalizeDateValue($user['last_posted_at'] ?? null);
        $accountType = $this->nonEmptyString($user['title'] ?? null);
        $externalLinks = $this->extractLinksFromDiscourseBio($user['bio_cooked'] ?? null);

        if ($websiteUrl !== null) {
            $externalLinks[] = $websiteUrl;
        }
        $externalLinks = array_values(array_unique(array_filter($externalLinks, static fn (string $value): bool => $value !== '')));

        $evidence = ['profile_url', 'display_name'];
        if ($avatarUrl !== null) {
            $evidence[] = 'avatar_url';
        }
        if ($bio !== null) {
            $evidence[] = 'bio';
        }
        if ($location !== null) {
            $evidence[] = 'location';
        }
        if ($websiteUrl !== null) {
            $evidence[] = 'website_url';
        }
        if ($createdAt !== null) {
            $evidence[] = 'created_at';
        }
        if ($lastActiveAt !== null) {
            $evidence[] = 'last_active_at';
        }
        if ($accountType !== null) {
            $evidence[] = 'account_type';
        }
        if ($externalLinks !== []) {
            $evidence[] = 'external_links';
        }
        $evidence[] = 'api_json';

        return [
            'display_name' => $displayName,
            'username' => $username,
            'avatar_url' => $avatarUrl,
            'bio' => $bio,
            'location' => $location,
            'website_url' => $websiteUrl,
            'created_at' => $createdAt,
            'last_active_at' => $lastActiveAt,
            'account_type' => $accountType,
            'external_links' => $externalLinks,
            'sources' => ['api_json'],
            'evidence' => array_values(array_unique($evidence)),
            'status_detail' => 'found',
            'observed_metadata_level' => 4,
            'platform' => $this->key(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function extractGithubStyleProfileMetadata(array $data, string $profileUrl, string $target): array
    {
        $username = $this->nonEmptyString($data['login'] ?? null)
            ?? $this->nonEmptyString($data['username'] ?? null);
        $avatarUrl = $this->normalizeAbsoluteUrl($data['avatar_url'] ?? ($data['avatar'] ?? null), $profileUrl);
        $createdAt = $this->normalizeDateValue($data['created_at'] ?? ($data['created'] ?? null));

        if ($username === null || ($avatarUrl === null && $createdAt === null && !array_key_exists('name', $data) && !array_key_exists('full_name', $data))) {
            return [];
        }

        $displayName = $this->nonEmptyString($data['name'] ?? null)
            ?? $this->nonEmptyString($data['full_name'] ?? null)
            ?? $username;
        $bio = $this->nonEmptyString($data['bio'] ?? null)
            ?? $this->nonEmptyString($data['description'] ?? null);
        $location = $this->nonEmptyString($data['location'] ?? null);
        $websiteUrl = $this->normalizeAbsoluteUrl($data['blog'] ?? ($data['website'] ?? null), $profileUrl);
        $publicEmail = $this->normalizePublicEmail($data['email'] ?? null);
        $followers = $this->normalizeIntegerValue($data['followers'] ?? ($data['followers_count'] ?? null));
        $following = $this->normalizeIntegerValue($data['following'] ?? ($data['following_count'] ?? null));
        $postsCount = $this->normalizeIntegerValue($data['public_repos'] ?? ($data['starred_repos_count'] ?? null));
        $accountType = $this->nonEmptyString($data['type'] ?? ($data['visibility'] ?? null));
        $lastActiveAt = $this->normalizeDateValue($data['updated_at'] ?? ($data['last_login'] ?? null));

        $externalLinks = [];
        $htmlUrl = $this->normalizeAbsoluteUrl($data['html_url'] ?? ($data['url'] ?? null), $profileUrl);
        if ($websiteUrl !== null) {
            $externalLinks[] = $websiteUrl;
        }
        if ($htmlUrl !== null && $htmlUrl !== $profileUrl) {
            $externalLinks[] = $htmlUrl;
        }
        $twitterUsername = $this->nonEmptyString($data['twitter_username'] ?? null);
        if ($twitterUsername !== null) {
            $externalLinks[] = 'https://x.com/' . ltrim($twitterUsername, '@');
        }
        $externalLinks = array_values(array_unique($externalLinks));

        $evidence = ['profile_url', 'display_name', 'api_json'];
        if ($avatarUrl !== null) {
            $evidence[] = 'avatar_url';
        }
        if ($bio !== null) {
            $evidence[] = 'bio';
        }
        if ($location !== null) {
            $evidence[] = 'location';
        }
        if ($websiteUrl !== null) {
            $evidence[] = 'website_url';
        }
        if ($publicEmail !== null) {
            $evidence[] = 'public_email';
        }
        if ($followers !== null) {
            $evidence[] = 'followers';
        }
        if ($following !== null) {
            $evidence[] = 'following';
        }
        if ($postsCount !== null) {
            $evidence[] = 'posts_count';
        }
        if ($createdAt !== null) {
            $evidence[] = 'created_at';
        }
        if ($lastActiveAt !== null) {
            $evidence[] = 'last_active_at';
        }
        if ($accountType !== null) {
            $evidence[] = 'account_type';
        }
        if ($externalLinks !== []) {
            $evidence[] = 'external_links';
        }

        return [
            'display_name' => $displayName,
            'username' => $username,
            'avatar_url' => $avatarUrl,
            'bio' => $bio,
            'location' => $location,
            'website_url' => $websiteUrl,
            'public_email' => $publicEmail,
            'followers' => $followers,
            'following' => $following,
            'posts_count' => $postsCount,
            'created_at' => $createdAt,
            'last_active_at' => $lastActiveAt,
            'account_type' => $accountType,
            'external_links' => $externalLinks,
            'sources' => ['api_json'],
            'evidence' => array_values(array_unique($evidence)),
            'status_detail' => 'found',
            'observed_metadata_level' => 4,
            'platform' => $this->key(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function extractDailymotionProfileMetadata(array $data, string $profileUrl, string $target): array
    {
        if (!array_key_exists('username', $data) || !array_key_exists('screenname', $data)) {
            return [];
        }

        $username = $this->nonEmptyString($data['username'] ?? null) ?? $target;
        $displayName = $this->nonEmptyString($data['screenname'] ?? null) ?? $username;
        $avatarUrl = $this->normalizeAbsoluteUrl($data['avatar_720_url'] ?? null, $profileUrl);
        $location = $this->nonEmptyString($data['country'] ?? null);
        $followers = $this->normalizeIntegerValue($data['followers_total'] ?? null);
        $following = $this->normalizeIntegerValue($data['following_total'] ?? null);
        $postsCount = $this->normalizeIntegerValue($data['videos_total'] ?? null);
        $createdAt = $this->normalizeDateValue($data['created_time'] ?? null);
        $isVerified = is_bool($data['verified'] ?? null) ? (bool) $data['verified'] : null;

        $evidence = ['profile_url', 'display_name', 'api_json'];
        if ($avatarUrl !== null) {
            $evidence[] = 'avatar_url';
        }
        if ($location !== null) {
            $evidence[] = 'location';
        }
        if ($followers !== null) {
            $evidence[] = 'followers';
        }
        if ($following !== null) {
            $evidence[] = 'following';
        }
        if ($postsCount !== null) {
            $evidence[] = 'posts_count';
        }
        if ($createdAt !== null) {
            $evidence[] = 'created_at';
        }
        if ($isVerified !== null) {
            $evidence[] = 'is_verified';
        }

        return [
            'display_name' => $displayName,
            'username' => $username,
            'avatar_url' => $avatarUrl,
            'location' => $location,
            'followers' => $followers,
            'following' => $following,
            'posts_count' => $postsCount,
            'created_at' => $createdAt,
            'is_verified' => $isVerified,
            'sources' => ['api_json'],
            'evidence' => array_values(array_unique($evidence)),
            'status_detail' => 'found',
            'observed_metadata_level' => 4,
            'platform' => $this->key(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function extractPx500ProfileMetadata(array $data, string $profileUrl, string $target): array
    {
        $user = data_get($data, 'data.userByUsername');
        if (!is_array($user)) {
            return [];
        }

        $username = $this->nonEmptyString($user['username'] ?? null) ?? $target;
        $displayName = $this->nonEmptyString($user['displayName'] ?? null)
            ?? $this->joinNonEmptyStrings([
                $this->nonEmptyString($user['firstName'] ?? null),
                $this->nonEmptyString($user['lastName'] ?? null),
            ])
            ?? $this->joinNonEmptyStrings([
                $this->nonEmptyString(data_get($user, 'userProfile.firstname')),
                $this->nonEmptyString(data_get($user, 'userProfile.lastname')),
            ])
            ?? $username;
        $bio = $this->nonEmptyString(data_get($user, 'userProfile.about'));
        $location = $this->joinNonEmptyStrings([
            $this->nonEmptyString(data_get($user, 'userProfile.city')),
            $this->nonEmptyString(data_get($user, 'userProfile.state')),
            $this->nonEmptyString(data_get($user, 'userProfile.country')),
        ]);
        $websiteUrl = $this->normalizeAbsoluteUrl(data_get($user, 'socialMedia.website'), $profileUrl);
        $createdAt = $this->normalizeDateValue($user['registeredAt'] ?? null);
        $externalLinks = array_values(array_unique(array_filter([
            $websiteUrl,
            $this->normalizeAbsoluteUrl(data_get($user, 'socialMedia.twitter'), $profileUrl),
            $this->normalizeAbsoluteUrl(data_get($user, 'socialMedia.facebook'), $profileUrl),
            $this->normalizeAbsoluteUrl(data_get($user, 'socialMedia.instagram'), $profileUrl),
        ], static fn (?string $value): bool => $value !== null && $value !== '')));

        $evidence = ['profile_url', 'display_name', 'api_json'];
        if ($bio !== null) {
            $evidence[] = 'bio';
        }
        if ($location !== null) {
            $evidence[] = 'location';
        }
        if ($websiteUrl !== null) {
            $evidence[] = 'website_url';
        }
        if ($createdAt !== null) {
            $evidence[] = 'created_at';
        }
        if ($externalLinks !== []) {
            $evidence[] = 'external_links';
        }

        return [
            'display_name' => $displayName,
            'username' => $username,
            'bio' => $bio,
            'location' => $location,
            'website_url' => $websiteUrl,
            'created_at' => $createdAt,
            'external_links' => $externalLinks,
            'sources' => ['api_json'],
            'evidence' => array_values(array_unique($evidence)),
            'status_detail' => 'found',
            'observed_metadata_level' => 4,
            'platform' => $this->key(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function extractCodewarsProfileMetadata(array $data, string $profileUrl, string $target): array
    {
        $username = $this->nonEmptyString($data['username'] ?? null);
        $honor = $this->normalizeIntegerValue($data['honor'] ?? null);
        $leaderboardPosition = $this->normalizeIntegerValue($data['leaderboardPosition'] ?? null);

        if ($username === null || ($honor === null && $leaderboardPosition === null && !is_array($data['codeChallenges'] ?? null))) {
            return [];
        }

        $codeChallengesCompleted = $this->normalizeIntegerValue(data_get($data, 'codeChallenges.totalCompleted'));
        $codeChallengesAuthored = $this->normalizeIntegerValue(data_get($data, 'codeChallenges.totalAuthored'));
        $rankName = $this->nonEmptyString(data_get($data, 'ranks.overall.name'));

        $evidence = ['profile_url', 'display_name', 'api_json', 'codewars_profile'];
        if ($honor !== null) {
            $evidence[] = 'honor';
        }
        if ($leaderboardPosition !== null) {
            $evidence[] = 'leaderboard_position';
        }
        if ($codeChallengesCompleted !== null) {
            $evidence[] = 'code_challenges_completed';
        }
        if ($codeChallengesAuthored !== null) {
            $evidence[] = 'code_challenges_authored';
        }
        if ($rankName !== null) {
            $evidence[] = 'rank_name';
        }

        return [
            'display_name' => $username,
            'username' => $username,
            'honor' => $honor,
            'leaderboard_position' => $leaderboardPosition,
            'code_challenges_completed' => $codeChallengesCompleted,
            'code_challenges_authored' => $codeChallengesAuthored,
            'rank_name' => $rankName,
            'sources' => ['api_json'],
            'evidence' => array_values(array_unique($evidence)),
            'status_detail' => 'found',
            'observed_metadata_level' => 4,
            'platform' => $this->key(),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    protected function extractCratesProfileMetadata(array $user, string $profileUrl, string $target): array
    {
        $username = $this->nonEmptyString($user['login'] ?? null) ?? $target;
        $displayName = $this->nonEmptyString($user['name'] ?? null) ?? $username;
        $avatarUrl = $this->normalizeAbsoluteUrl($user['avatar'] ?? null, $profileUrl);
        $websiteUrl = $this->normalizeAbsoluteUrl($user['url'] ?? null, $profileUrl);
        $createdAt = $this->normalizeDateValue($user['created_at'] ?? null);

        if ($avatarUrl === null && $websiteUrl === null && $createdAt === null && !array_key_exists('name', $user)) {
            return [];
        }

        $evidence = ['profile_url', 'display_name', 'api_json'];
        if ($avatarUrl !== null) {
            $evidence[] = 'avatar_url';
        }
        if ($websiteUrl !== null) {
            $evidence[] = 'website_url';
        }
        if ($createdAt !== null) {
            $evidence[] = 'created_at';
        }

        return [
            'display_name' => $displayName,
            'username' => $username,
            'avatar_url' => $avatarUrl,
            'website_url' => $websiteUrl,
            'external_links' => $websiteUrl !== null ? [$websiteUrl] : [],
            'created_at' => $createdAt,
            'sources' => ['api_json'],
            'evidence' => array_values(array_unique($evidence)),
            'status_detail' => 'found',
            'observed_metadata_level' => 4,
            'platform' => $this->key(),
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    protected function metadataSummary(array $metadata): string
    {
        $lines = [];

        foreach ($metadata as $label => $value) {
            $formatted = $this->formatMetadataValue($value);
            if ($formatted === null) {
                continue;
            }

            $lines[] = $label . ': ' . $formatted;
        }

        return implode("\n", $lines);
    }

    private function resolveTemplateUrl(string $url, string $target): ?string
    {
        $resolved = str_replace(
            ['{user}', '{username}', '{target}'],
            rawurlencode($target),
            $url
        );

        if ($resolved !== $url && str_contains($resolved, rawurlencode($target))) {
            return $resolved;
        }

        return null;
    }

    private function looksLikePublicProfileUrl(string $url, string $target): bool
    {
        $parsed = parse_url($url);
        if (!is_array($parsed)) {
            return false;
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));
        $path = strtolower((string) ($parsed['path'] ?? ''));
        $query = strtolower((string) ($parsed['query'] ?? ''));
        $targetLower = strtolower($target);

        if (!str_contains($host, $targetLower) && !str_contains($path, $targetLower) && !str_contains($query, $targetLower)) {
            return false;
        }

        foreach ([
            '/api/',
            '/graphql',
            '/ajax/',
            '/signup',
            '/register',
            '/verify',
            '/check',
            '/lookup',
            '/search',
            '/w/api.php',
            '/exists',
        ] as $needle) {
            if (str_contains($path, $needle) || str_contains($query, trim($needle, '/'))) {
                return false;
            }
        }

        return true;
    }

    private function formatMetadataValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            $formatted = trim((string) $value);

            return $formatted !== '' ? $formatted : null;
        }

        if (is_array($value)) {
            $items = $this->collectScalarValues($value);

            return $items !== [] ? implode(', ', $items) : null;
        }

        return null;
    }

    private function nonEmptyString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function resolveAvatarTemplateUrl(mixed $value, string $baseUrl): ?string
    {
        $template = $this->nonEmptyString($value);
        if ($template === null) {
            return null;
        }

        return $this->normalizeAbsoluteUrl(str_replace('{size}', '512', $template), $baseUrl);
    }

    private function normalizeAbsoluteUrl(mixed $value, string $baseUrl): ?string
    {
        $url = $this->nonEmptyString($value);
        if ($url === null) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';

            return $scheme . ':' . $url;
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        if (!str_starts_with($url, '/')) {
            return $url;
        }

        $parts = parse_url($baseUrl);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        return $parts['scheme'] . '://' . $parts['host'] . $url;
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            try {
                return (new \DateTimeImmutable('@' . (string) $value))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(\DateTimeInterface::ATOM);
            } catch (\Throwable) {
                return null;
            }
        }

        $date = $this->nonEmptyString($value);
        if ($date === null) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($date))->format(\DateTimeInterface::ATOM);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractLinksFromDiscourseBio(mixed $value): array
    {
        $html = $this->nonEmptyString($value);
        if ($html === null) {
            return [];
        }

        if (preg_match_all('/href="([^"]+)"/i', $html, $matches) !== false) {
            return array_values(array_unique(array_filter(
                array_map(static fn (string $link): string => trim($link), $matches[1]),
                static fn (string $link): bool => $link !== ''
            )));
        }

        return [];
    }

    private function normalizeIntegerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function normalizePublicEmail(mixed $value): ?string
    {
        $email = $this->nonEmptyString($value);
        if ($email === null || !str_contains($email, '@')) {
            return null;
        }

        return $email;
    }

    /**
     * @param array<int, string|null> $parts
     */
    private function joinNonEmptyStrings(array $parts): ?string
    {
        $normalized = array_values(array_filter($parts, static fn (?string $value): bool => $value !== null && $value !== ''));
        if ($normalized === []) {
            return null;
        }

        return implode(', ', $normalized);
    }

    /**
     * @return array<int, string>
     */
    private function collectScalarValues(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $formatted = trim((string) $item);
                if ($formatted !== '') {
                    $items[] = $formatted;
                }
            }
        }

        return array_values(array_unique($items));
    }

    private function rememberRequestDiagnostics(Response $response, mixed $proxy, float $startedAt): void
    {
        $this->lastRequestDiagnostics = $this->buildRequestDiagnosticsPayload(
            $proxy,
            $response->status(),
            microtime(true) - $startedAt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequestDiagnosticsPayload(mixed $proxy, ?int $httpStatus, float $durationSeconds): array
    {
        $metadata = [
            'latency_ms' => max(0, (int) round($durationSeconds * 1000)),
        ];

        if ($httpStatus !== null) {
            $metadata['http_status'] = $httpStatus;
        }

        $proxyUsed = $this->sanitizeProxyLabel($proxy);
        if ($proxyUsed !== null) {
            $metadata['proxy_used'] = $proxyUsed;
        }

        return $metadata;
    }

    private function sanitizeProxyLabel(mixed $proxy): ?string
    {
        if (!is_scalar($proxy)) {
            return null;
        }

        $proxyString = trim((string) $proxy);
        if ($proxyString === '') {
            return null;
        }

        if (!preg_match('/^[a-z0-9]+:\/\//i', $proxyString)) {
            $proxyString = 'http://' . $proxyString;
        }

        $parts = parse_url($proxyString);
        if ($parts === false) {
            return trim((string) $proxy);
        }

        $host = trim((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return trim((string) $proxy);
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $host . $port;
    }
}
