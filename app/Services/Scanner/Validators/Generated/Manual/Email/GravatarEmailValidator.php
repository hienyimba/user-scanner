<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Support\Facades\Http;

final class GravatarEmailValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'gravatar';
    }

    public function category(): string
    {
        return 'social';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Gravatar';
    }

    public function siteUrl(): string
    {
        return 'https://gravatar.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $startedAt = microtime(true);
        $normalizedEmail = strtolower(trim($target));
        $hashMd5 = md5($normalizedEmail);
        $hashSha256 = hash('sha256', $normalizedEmail);

        try {
            $request = Http::timeout(10)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->withHeaders([
                    'User-Agent' => (string) config('scanner.user_agent'),
                    'Accept' => 'application/json',
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->get('https://en.gravatar.com/' . $hashMd5 . '.json');
            if ($response->status() === 404 || str_contains($response->body(), 'User not found')) {
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

            $entry = data_get($response->json(), 'entry.0');
            if (!is_array($entry)) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Error',
                    'Unexpected response body',
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
                );
            }

            $username = $this->nonEmptyStringValue($entry['preferredUsername'] ?? null);
            $profileUrl = $username !== null
                ? 'https://gravatar.com/' . rawurlencode($username)
                : 'https://gravatar.com/' . $hashMd5;

            $metadata = [
                'hash_md5' => $hashMd5,
                'hash_sha256' => $hashSha256,
                'source' => 'gravatar',
                'sources' => ['api_json', 'gravatar_profile'],
                'profile_url' => $profileUrl,
            ];

            if ($username !== null) {
                $metadata['username'] = $username;
            }

            $displayName = $this->nonEmptyStringValue(data_get($entry, 'name.formatted') ?? ($entry['displayName'] ?? null));
            if ($displayName !== null) {
                $metadata['display_name'] = $displayName;
            }

            $avatarUrl = $this->normalizeAbsoluteUrlValue($entry['thumbnailUrl'] ?? null, 'https://gravatar.com');
            if ($avatarUrl !== null) {
                $metadata['avatar_url'] = $avatarUrl;
            }

            $bio = $this->nonEmptyStringValue($entry['aboutMe'] ?? null);
            if ($bio !== null) {
                $metadata['bio'] = $bio;
            }

            $location = $this->nonEmptyStringValue($entry['currentLocation'] ?? null);
            if ($location !== null) {
                $metadata['location'] = $location;
            }

            $emails = [];
            foreach ((array) ($entry['emails'] ?? []) as $email) {
                if (!is_array($email)) {
                    continue;
                }

                $publicEmail = $this->normalizePublicEmailValue($email['value'] ?? null);
                if ($publicEmail !== null) {
                    $emails[] = $publicEmail;
                }
            }
            if ($emails !== []) {
                $metadata['public_email'] = $emails[0];
                $metadata['emails'] = array_values(array_unique($emails));
            }

            $links = [];
            foreach ((array) ($entry['accounts'] ?? []) as $account) {
                if (!is_array($account)) {
                    continue;
                }

                $url = $this->normalizeAbsoluteUrlValue($account['url'] ?? null, 'https://gravatar.com');
                if ($url !== null) {
                    $links[] = $url;
                }
            }
            foreach ((array) ($entry['urls'] ?? []) as $urlEntry) {
                if (!is_array($urlEntry)) {
                    continue;
                }

                $url = $this->normalizeAbsoluteUrlValue($urlEntry['value'] ?? null, 'https://gravatar.com');
                if ($url !== null) {
                    $links[] = $url;
                }
            }
            if ($links !== []) {
                $metadata['external_links'] = array_values(array_unique($links));
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
                confidence: 0.98,
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
