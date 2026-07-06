<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class LibravatarEmailValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'libravatar';
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
        return 'Libravatar';
    }

    public function siteUrl(): string
    {
        return 'https://www.libravatar.org';
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
                    'Accept' => 'image/avif,image/webp,image/png,image/*,*/*;q=0.8',
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $this->fetchAvatarResponse($request, $hashSha256, $hashMd5);
            if ($response === null) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Not Registered',
                    '',
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->requestDiagnostics($options, null, $startedAt),
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

            $contentType = strtolower((string) $response->header('Content-Type'));
            if ($contentType !== '' && !str_contains($contentType, 'image/')) {
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

            $effectiveUrl = $this->normalizeAbsoluteUrlValue(
                (string) ($response->effectiveUri() ?? ''),
                'https://seccdn.libravatar.org',
            );
            $avatarUrl = $effectiveUrl
                ?? 'https://seccdn.libravatar.org/avatar/' . $hashSha256 . '?d=404';
            $source = $this->inferAvatarSource($avatarUrl);

            $metadata = [
                'avatar_url' => $avatarUrl,
                'hash_md5' => $hashMd5,
                'hash_sha256' => $hashSha256,
                'source' => $source,
                'sources' => ['avatar_image_api', 'email_hash'],
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
                confidence: $source === 'libravatar' ? 0.82 : 0.7,
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

    private function fetchAvatarResponse(PendingRequest $request, string $hashSha256, string $hashMd5): ?\Illuminate\Http\Client\Response
    {
        foreach ([$hashSha256, $hashMd5] as $hash) {
            $response = $request->get('https://seccdn.libravatar.org/avatar/' . $hash, [
                'd' => '404',
            ]);

            if ($response->status() === 404) {
                continue;
            }

            return $response;
        }

        return null;
    }

    private function inferAvatarSource(string $avatarUrl): string
    {
        $host = strtolower((string) parse_url($avatarUrl, PHP_URL_HOST));

        if (str_contains($host, 'gravatar.com')) {
            return 'gravatar_fallback';
        }

        return 'libravatar';
    }
}
