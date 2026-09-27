<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class GithubValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'github';
    }

    public function category(): string
    {
        return 'dev';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Github';
    }

    public function siteUrl(): string
    {
        return 'https://github.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $startedAt = microtime(true);
        $cookieJar = new CookieJar;

        try {
            $request = Http::timeout(10)
                ->withOptions([
                    'allow_redirects' => true,
                    'cookies' => $cookieJar,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ]);

            if (! empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            // A public-profile email match is sufficient to prove that the
            // address is registered, and remains useful when /signup is behind
            // GitHub's bot challenge. A miss is not proof of availability, so
            // it must always fall through to the signup availability check.
            $publicMatch = $this->findExactPublicEmailMatch($request, $target);
            if ($publicMatch !== null) {
                $githubData = $publicMatch['profile'];
                $profileResponse = $publicMatch['response'];
                $username = (string) $githubData['login'];
                $profileUrl = $this->normalizeAbsoluteUrlValue(
                    $githubData['html_url'] ?? null,
                    'https://github.com',
                ) ?? 'https://github.com/'.rawurlencode($username);
                $metadata = array_replace(
                    $this->extractGithubStyleProfileMetadata($githubData, $profileUrl, $username),
                    [
                        'username' => $username,
                        'public_email' => $target,
                        'account_exists' => true,
                        'matched_by' => 'public profile email',
                        'sources' => ['github_user_search', 'github_public_api'],
                        'evidence_types' => ['exact_public_profile_email'],
                    ],
                );

                $githubId = $githubData['id'] ?? null;
                if (is_numeric($githubId)) {
                    $metadata['user_id'] = (int) $githubId;
                } elseif (is_scalar($githubId) && trim((string) $githubId) !== '') {
                    $metadata['user_id'] = trim((string) $githubId);
                }

                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Registered',
                    '',
                    mode: $this->mode(),
                    key: $this->key(),
                    profileUrl: $profileUrl,
                    confidence: 0.99,
                    metadata: $this->mergeRequestDiagnostics($metadata, $options, $profileResponse, $startedAt),
                );
            }

            $signup = $request->withHeaders([
                'host' => 'github.com',
                'sec-ch-ua' => '"Not(A:Brand";v="8", "Chromium";v="144", "Google Chrome";v="144"',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-platform' => '"Linux"',
                'upgrade-insecure-requests' => '1',
                'user-agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'sec-fetch-site' => 'cross-site',
                'sec-fetch-mode' => 'navigate',
                'sec-fetch-user' => '?1',
                'sec-fetch-dest' => 'document',
                'referer' => 'https://www.google.com/',
                'accept-encoding' => 'identity',
                'accept-language' => 'en-US,en;q=0.9',
                'priority' => 'u=0, i',
            ])->get('https://github.com/signup');

            if ($blocked = $this->detectBlockedOrChallenged($signup)) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    $blocked[0],
                    $blocked[1],
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $signup, $startedAt),
                );
            }

            $authenticityToken = $this->extractSignupAuthenticityToken($signup->body());
            if ($authenticityToken === null) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Error',
                    'Failed to extract GitHub authenticity_token',
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $signup, $startedAt),
                );
            }

            $response = $request->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                'Accept-Encoding' => 'identity',
                'sec-ch-ua-platform' => '"Linux"',
                'sec-ch-ua' => '"Not(A:Brand";v="8", "Chromium";v="144", "Google Chrome";v="144"',
                'sec-ch-ua-mobile' => '?0',
                'origin' => 'https://github.com',
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-dest' => 'empty',
                'referer' => 'https://github.com/signup',
                'accept-language' => 'en-US,en;q=0.9',
                'priority' => 'u=1, i',
            ])->asForm()->post('https://github.com/email_validity_checks', [
                'authenticity_token' => $authenticityToken,
                'value' => $target,
            ]);

            if ($blocked = $this->detectBlockedOrChallenged($response)) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    $blocked[0],
                    $blocked[1],
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
                );
            }

            $body = $response->body();
            if ($response->status() === 200 && str_contains($body, 'Email is available')) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Not Registered',
                    '',
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
                );
            }

            if (! str_contains($body, 'already associated with an account')) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Error',
                    'Unexpected status code: '.$response->status().', report this via GitHub issues',
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
                );
            }

            $metadata = [
                'sources' => ['github_signup_email_validity_check'],
                'account_exists' => true,
                'evidence_types' => ['github_signup_email_validity_check'],
            ];
            $profileUrl = null;
            $confidence = 0.88;

            $gravatarEntry = $this->fetchGravatarEntry($request, $target);
            if ($gravatarEntry !== null) {
                $metadata['sources'][] = 'gravatar_profile';
                $metadata['evidence_types'][] = 'gravatar_profile';
                $metadata['gravatar_hash_md5'] = md5(strtolower(trim($target)));

                $githubProfileUrl = $this->extractGithubProfileUrl($gravatarEntry);
                if ($githubProfileUrl !== null) {
                    $githubUsername = $this->extractGithubUsername($githubProfileUrl);
                    $profileUrl = $githubProfileUrl;
                    $metadata['profile_url'] = $githubProfileUrl;
                    $metadata['evidence_types'][] = 'public_profile_link';

                    if ($githubUsername !== null) {
                        $profileResponse = $request
                            ->withHeaders(['Accept' => 'application/vnd.github+json'])
                            ->get('https://api.github.com/users/'.rawurlencode($githubUsername));

                        if ($profileResponse->status() === 200 && is_array($profileResponse->json())) {
                            $githubData = $profileResponse->json();
                            $profileMetadata = $this->extractGithubStyleProfileMetadata($githubData, $githubProfileUrl, $githubUsername);
                            $existingSources = (array) ($metadata['sources'] ?? []);
                            $existingEvidenceTypes = (array) ($metadata['evidence_types'] ?? []);
                            $metadata = array_merge($metadata, $profileMetadata);
                            $metadata['username'] = $profileMetadata['username'] ?? $githubUsername;
                            $metadata['sources'] = array_values(array_unique(array_merge(
                                $existingSources,
                                (array) ($profileMetadata['sources'] ?? []),
                                ['github_public_api'],
                            )));
                            $metadata['evidence_types'] = array_values(array_unique(array_merge(
                                $existingEvidenceTypes,
                                ['github_public_api'],
                            )));

                            $githubId = $githubData['id'] ?? null;
                            if (is_numeric($githubId)) {
                                $metadata['user_id'] = (int) $githubId;
                            } elseif (is_scalar($githubId) && trim((string) $githubId) !== '') {
                                $metadata['user_id'] = trim((string) $githubId);
                            }

                            $company = $this->nonEmptyStringValue($githubData['company'] ?? null);
                            if ($company !== null) {
                                $metadata['company'] = $company;
                            }

                            $confidence = 0.97;
                        }
                    }
                }
            }

            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Registered',
                '',
                mode: $this->mode(),
                key: $this->key(),
                profileUrl: $profileUrl,
                confidence: $confidence,
                metadata: $this->mergeRequestDiagnostics($metadata, $options, $response, $startedAt),
            );
        } catch (\Throwable $e) {
            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Error',
                'unexpected exception: '.$e->getMessage(),
                mode: $this->mode(),
                key: $this->key(),
                metadata: $this->requestDiagnostics($options, null, $startedAt),
            );
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }

    /**
     * @return array{profile: array<string, mixed>, response: Response}|null
     */
    private function findExactPublicEmailMatch(PendingRequest $request, string $email): ?array
    {
        try {
            $search = $request
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get('https://api.github.com/search/users', [
                    'q' => $email.' in:email',
                    'per_page' => 5,
                ]);
        } catch (\Throwable) {
            return null;
        }

        if ($search->status() !== 200) {
            return null;
        }

        foreach ((array) ($search->json('items') ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $login = $this->nonEmptyStringValue($item['login'] ?? null);
            if ($login === null) {
                continue;
            }

            try {
                $profileResponse = $request
                    ->withHeaders(['Accept' => 'application/vnd.github+json'])
                    ->get('https://api.github.com/users/'.rawurlencode($login));
            } catch (\Throwable) {
                continue;
            }
            $profile = $profileResponse->json();

            if (
                $profileResponse->status() === 200
                && is_array($profile)
                && isset($profile['login'])
                && strcasecmp(trim((string) ($profile['email'] ?? '')), trim($email)) === 0
            ) {
                return ['profile' => $profile, 'response' => $profileResponse];
            }
        }

        return null;
    }

    private function extractSignupAuthenticityToken(string $html): ?string
    {
        if (preg_match(
            '/<auto-check\b[^>]*\bsrc="\/email_validity_checks"[^>]*>[\s\S]*?<input\b(?=[^>]*\bdata-csrf="true")(?=[^>]*\bvalue="([^"]+)")[^>]*>/i',
            $html,
            $match,
        ) === 1) {
            return $match[1];
        }

        // Older markup and recorded fixtures omit the auto-check wrapper. It
        // is still safe to accept the page when it exposes exactly one CSRF
        // input; multiple tokens remain ambiguous and must not be guessed.
        preg_match_all(
            '/<input\b(?=[^>]*\bdata-csrf="true")(?=[^>]*\bvalue="([^"]+)")[^>]*>/i',
            $html,
            $matches,
        );

        return count($matches[1] ?? []) === 1 ? $matches[1][0] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchGravatarEntry(PendingRequest $request, string $email): ?array
    {
        $hash = md5(strtolower(trim($email)));
        $response = $request
            ->withHeaders(['Accept' => 'application/json'])
            ->get('https://en.gravatar.com/'.$hash.'.json');

        if ($response->status() !== 200) {
            return null;
        }

        $entry = data_get($response->json(), 'entry.0');

        return is_array($entry) ? $entry : null;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function extractGithubProfileUrl(array $entry): ?string
    {
        $links = [];

        foreach ((array) ($entry['accounts'] ?? []) as $account) {
            if (! is_array($account)) {
                continue;
            }

            $url = $this->normalizeAbsoluteUrlValue($account['url'] ?? null, 'https://gravatar.com');
            if ($url !== null) {
                $links[] = $url;
            }
        }

        foreach ((array) ($entry['urls'] ?? []) as $urlEntry) {
            if (! is_array($urlEntry)) {
                continue;
            }

            $url = $this->normalizeAbsoluteUrlValue($urlEntry['value'] ?? null, 'https://gravatar.com');
            if ($url !== null) {
                $links[] = $url;
            }
        }

        foreach (array_values(array_unique($links)) as $url) {
            $parts = parse_url($url);
            $host = strtolower((string) ($parts['host'] ?? ''));
            if ($host !== 'github.com' && $host !== 'www.github.com') {
                continue;
            }

            $path = trim((string) ($parts['path'] ?? ''), '/');
            if ($path === '' || str_contains($path, '/')) {
                continue;
            }

            return 'https://github.com/'.$path;
        }

        return null;
    }

    private function extractGithubUsername(string $profileUrl): ?string
    {
        $parts = parse_url($profileUrl);
        if ($parts === false) {
            return null;
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        if ($path === '' || str_contains($path, '/')) {
            return null;
        }

        return $path;
    }
}
