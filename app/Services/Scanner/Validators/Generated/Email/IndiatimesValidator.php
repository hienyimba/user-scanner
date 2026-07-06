<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class IndiatimesValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'indiatimes';
    }

    public function category(): string
    {
        return 'news';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Indiatimes';
    }

    public function siteUrl(): string
    {
        return 'https://timesofindia.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $startedAt = microtime(true);

        try {
            $request = Http::timeout(5)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36',
                'Content-Type' => 'application/json',
                'sdkversion' => '0.8.1',
                'channel' => 'toi',
                'platform' => 'WAP',
                'Origin' => 'https://timesofindia.indiatimes.com',
                'Referer' => 'https://timesofindia.indiatimes.com/',
                'Accept-Encoding' => 'identity',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->withBody(json_encode(['identifier' => $target], JSON_THROW_ON_ERROR), 'application/json')
                ->post('https://jsso.indiatimes.com/sso/crossapp/identity/web/checkUserExists');
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

            $userStatus = (string) $response->json('data.status');
            if ($userStatus === 'VERIFIED_EMAIL') {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Registered',
                    '',
                    mode: $this->mode(),
                    key: $this->key(),
                    confidence: 0.88,
                    metadata: $this->mergeRequestDiagnostics([
                        'public_email' => $target,
                        'account_exists' => true,
                        'email_verification_status' => 'verified',
                        'is_verified' => true,
                        'sources' => ['api_json', 'identity_api'],
                    ], $options, $response, $startedAt),
                );
            }
            if ($userStatus === 'UNREGISTERED_EMAIL') {
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
            if ($userStatus === 'UNVERIFIED_EMAIL') {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Registered',
                    '',
                    'However email is not verified on the site',
                    mode: $this->mode(),
                    key: $this->key(),
                    confidence: 0.84,
                    metadata: $this->mergeRequestDiagnostics([
                        'public_email' => $target,
                        'account_exists' => true,
                        'email_verification_status' => 'unverified',
                        'is_verified' => false,
                        'sources' => ['api_json', 'identity_api'],
                    ], $options, $response, $startedAt),
                );
            }

            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Error',
                'Unexpected response body, report it on github',
                mode: $this->mode(),
                key: $this->key(),
                metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
            );
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = str_contains($message, 'timed out')
                ? (str_contains($message, 'read') ? 'Server took too long to respond (Read Timeout)' : 'Connection timed out! maybe region blocks')
                : $e->getMessage();
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

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
