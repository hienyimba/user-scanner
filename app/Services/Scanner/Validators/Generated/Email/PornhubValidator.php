<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class PornhubValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'pornhub';
    }

    public function category(): string
    {
        return 'adult';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Pornhub';
    }

    public function siteUrl(): string
    {
        return 'https://pornhub.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $baseUrl = 'https://www.pornhub.com';
        $showUrl = 'https://pornhub.com';
        $checkApi = $baseUrl . '/api/v1/user/create_account_check';
        $headers = [
            'user-agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36',
            'x-requested-with' => 'XMLHttpRequest',
            'origin' => $baseUrl,
            'referer' => $baseUrl . '/',
            'content-type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        ];

        try {
            $client = Http::timeout(5)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                    'version' => 2.0,
                ])
                ->withHeaders($headers);

            if (!empty($options['proxy'])) {
                $client = $client->withOptions(['proxy' => $options['proxy']]);
            }

            $landing = $client->get($baseUrl);
            if (!preg_match('/var\s+token\s*=\s*"([^"]+)"/', $landing->body(), $matches)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Failed to extract dynamic token from HTML', mode: $this->mode(), key: $this->key());
            }

            $response = $client
                ->asForm()
                ->post($checkApi . '?token=' . urlencode($matches[1]), [
                    'check_what' => 'email',
                    'email' => $target,
                ]);

            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Rate limited, wait for a few minutes', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'HTTP Error: ' . $response->status(), mode: $this->mode(), key: $this->key());
            }

            $data = $response->json();
            $status = $data['email'] ?? null;
            $error = (string) ($data['error_message'] ?? '');
            $body = $response->body();
            $emailDomain = substr(strrchr($target, '@') ?: '', 1);

            if ($status === 'create_account_failed') {
                if (str_contains($error, 'Email extension')) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Not Registered', "Domain '{$emailDomain}' is not allowed by PornHub", mode: $this->mode(), key: $this->key());
                }
                if (str_contains($error, 'delivery issues')) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'The email is experiencing email delivery issues', mode: $this->mode(), key: $this->key());
                }
            }

            if ($status === 'create_account_passed') {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (str_contains(strtolower($error), 'already in use') || str_contains($error, 'already registered')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Registered', '', mode: $this->mode(), key: $this->key());
            }

            // Live API sometimes returns a different key layout than the older Python module expects.
            $bodyLower = strtolower($body);
            if (
                str_contains($bodyLower, 'already in use')
                || str_contains($bodyLower, 'already registered')
                || str_contains($bodyLower, 'email has already been used')
            ) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (
                str_contains($bodyLower, 'create_account_passed')
                || str_contains($bodyLower, '"result":"ok"')
                || str_contains($bodyLower, '"valid":true')
            ) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (str_contains($bodyLower, 'email extension')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Not Registered', "Domain '{$emailDomain}' is not allowed by PornHub", mode: $this->mode(), key: $this->key());
            }
            if (str_contains($bodyLower, 'delivery issues')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'The email is experiencing email delivery issues', mode: $this->mode(), key: $this->key());
            }

            $snippet = trim(preg_replace('/\s+/', ' ', substr($body, 0, 180)) ?? '');
            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Unexpected API response: ' . $status . ': ' . $error . ($snippet !== '' ? ' | ' . $snippet : ''), mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Pornhub uses a custom token flow'];
    }
}
