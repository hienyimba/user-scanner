<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class MozValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'moz';
    }

    public function category(): string
    {
        return 'other';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Moz';
    }

    public function siteUrl(): string
    {
        return 'https://moz.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(10)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
                'Content-Type' => 'application/json',
                'sec-ch-ua-platform' => '"Android"',
                'origin' => 'https://moz.com',
                'referer' => 'https://moz.com/checkout/freetrial/signup/pro_medium/monthly',
                'accept-language' => 'en-US,en;q=0.9',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $payload = [
                'jsonrpc' => '2.0',
                'id' => (string) Str::uuid(),
                'method' => 'user.create.validate',
                'params' => [
                    'data' => [
                        'create_session' => true,
                        'verification_email_redirect' => '/checkout/freetrial/billing-payment/pro_medium/monthly',
                        'user' => [
                            'email' => $target,
                            'password' => 'W3n3v3r_t0uch3d_s0ftn33s',
                        ],
                    ],
                ],
            ];

            $response = $request->withBody(json_encode($payload, JSON_THROW_ON_ERROR), 'application/json')
                ->post('https://moz.com/app-api/jsonrpc/user.create.validate');

            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited wait for few minutes', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'HTTP Error: ' . $response->status(), mode: $this->mode(), key: $this->key());
            }

            $errors = $response->json('result.errors') ?? [];
            if ($errors === []) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            foreach ($errors as $error) {
                if (($error['data']['issue'] ?? null) === 'param-is-duplicate') {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
                }
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response body structure, report it via GitHub issues', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = str_contains($message, 'timed out')
                ? (str_contains($message, 'read') ? 'Server took too long to respond (Read Timeout)' : 'Connection timed out! maybe region blocks')
                : $e->getMessage();

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }
}
