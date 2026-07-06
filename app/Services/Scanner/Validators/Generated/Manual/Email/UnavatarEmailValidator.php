<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Support\Facades\Http;

final class UnavatarEmailValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'unavatar';
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
        return 'Unavatar';
    }

    public function siteUrl(): string
    {
        return 'https://unavatar.io';
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

            $response = $request->get('https://unavatar.io/email/' . rawurlencode($target), [
                'json' => 'true',
                'fallback' => 'false',
            ]);

            if ($response->status() === 404) {
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

            if ($response->status() !== 200) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Error',
                    'Unexpected response status: ' . $response->status(),
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
                );
            }

            $avatarUrl = $this->normalizeAbsoluteUrlValue(
                data_get($response->json(), 'url'),
                'https://unavatar.io',
            );
            if ($avatarUrl === null) {
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

            $metadata = [
                'avatar_url' => $avatarUrl,
                'hash_md5' => $hashMd5,
                'hash_sha256' => $hashSha256,
                'source' => $this->inferAvatarSource($avatarUrl),
                'sources' => ['avatar_json_api', 'email_hash'],
            ];

            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Registered',
                '',
                mode: $this->mode(),
                key: $this->key(),
                confidence: 0.72,
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

    private function inferAvatarSource(string $avatarUrl): string
    {
        $host = strtolower((string) parse_url($avatarUrl, PHP_URL_HOST));

        return match (true) {
            str_contains($host, 'gravatar.com') => 'gravatar',
            str_contains($host, 'githubusercontent.com'),
            str_contains($host, 'github.com') => 'github',
            default => 'unavatar',
        };
    }
}
