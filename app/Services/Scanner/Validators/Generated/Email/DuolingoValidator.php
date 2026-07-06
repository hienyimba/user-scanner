<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Support\Facades\Http;

final class DuolingoValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'duolingo';
    }

    public function category(): string
    {
        return 'learning';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Duolingo';
    }

    public function siteUrl(): string
    {
        return 'https://duolingo.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $startedAt = microtime(true);

        try {
            $request = Http::timeout(5)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->withHeaders([
                    'authority' => 'www.duolingo.com',
                    'Accept' => 'application/json, text/plain, */*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0',
                    'Referer' => 'https://www.duolingo.com/',
                    'Accept-Encoding' => 'identity',
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->get('https://www.duolingo.com/2017-06-30/users', [
                'email' => $target,
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

            if ($response->status() !== 200) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Error',
                    'HTTP ' . $response->status(),
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
                );
            }

            $users = $response->json('users');
            if (!is_array($users) || $users === []) {
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

            $user = is_array($users[0] ?? null) ? $users[0] : null;
            if ($user === null) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Registered',
                    '',
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([
                        'sources' => ['api_json', 'duolingo_users_api'],
                        'account_exists' => true,
                    ], $options, $response, $startedAt),
                    confidence: 0.85,
                );
            }

            $metadata = [
                'sources' => ['api_json', 'duolingo_users_api'],
                'account_exists' => true,
            ];

            $username = $this->nonEmptyStringValue($user['username'] ?? null);
            if ($username !== null) {
                $metadata['username'] = $username;
            }

            $displayName = $this->nonEmptyStringValue($user['name'] ?? ($user['display_name'] ?? null));
            if ($displayName !== null) {
                $metadata['display_name'] = $displayName;
            }

            $userId = $user['id'] ?? ($user['user_id'] ?? null);
            if (is_numeric($userId)) {
                $metadata['user_id'] = (int) $userId;
            } elseif (is_scalar($userId) && trim((string) $userId) !== '') {
                $metadata['user_id'] = trim((string) $userId);
            }

            $avatarUrl = $this->normalizeAbsoluteUrlValue(
                $user['picture'] ?? ($user['avatar'] ?? ($user['avatar_url'] ?? null)),
                'https://www.duolingo.com'
            );
            if ($avatarUrl !== null) {
                $metadata['avatar_url'] = $avatarUrl;
            }

            foreach ([
                'has_google_id' => ['hasGoogleId', 'has_google_id'],
                'has_facebook_id' => ['hasFacebookId', 'has_facebook_id'],
                'has_plus' => ['hasPlus', 'has_plus'],
            ] as $targetKey => $sourceKeys) {
                foreach ($sourceKeys as $sourceKey) {
                    if (is_bool($user[$sourceKey] ?? null)) {
                        $metadata[$targetKey] = (bool) $user[$sourceKey];
                        break;
                    }
                }
            }

            $lastActiveAt = $this->normalizeDateMetadataValue(
                $user['lastAccessed'] ?? ($user['last_active_at'] ?? ($user['lastActivityAt'] ?? null))
            );
            if ($lastActiveAt !== null) {
                $metadata['last_active_at'] = $lastActiveAt;
                $metadata['has_recent_activity'] = true;
            }

            $profileUrl = $username !== null ? 'https://www.duolingo.com/profile/' . rawurlencode($username) : null;

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
                confidence: $profileUrl !== null ? 0.96 : 0.88,
                metadata: $this->mergeRequestDiagnostics($metadata, $options, $response, $startedAt),
            );
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = str_contains($message, 'timed out') ? 'Connection timed out' : $e->getMessage();

            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Error',
                $reason,
                mode: $this->mode(),
                key: $this->key(),
                metadata: $this->requestDiagnostics($options, null, $startedAt),
            );
        }
    }
}
