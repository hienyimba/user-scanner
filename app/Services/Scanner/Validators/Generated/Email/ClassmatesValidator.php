<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class ClassmatesValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'classmates';
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
        return 'Classmates';
    }

    public function siteUrl(): string
    {
        return 'https://www.classmates.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $showUrl = $this->siteUrl();
        $loginUrl = 'https://www.classmates.com/auth/login';
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Encoding' => 'identity',
            'sec-ch-ua-platform' => '"Android"',
            'Origin' => 'https://www.classmates.com',
            'Referer' => $loginUrl,
            'Accept-Language' => 'en-US,en;q=0.9',
        ];

        try {
            $client = Http::timeout(15)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->withHeaders($headers);

            if (!empty($options['proxy'])) {
                $client = $client->withOptions(['proxy' => $options['proxy']]);
            }

            $init = $client->get($loginUrl);
            if ($init->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Caught by WAF (403) during Handshake', mode: $this->mode(), key: $this->key());
            }

            if (!preg_match('/name="_csrf" value="([^"]+)"/', $init->body(), $matches)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Failed to extract CSRF token from login page', mode: $this->mode(), key: $this->key());
            }

            $payload = [
                '_csrf' => $matches[1],
                'successUrl' => '',
                'emailOrRegId' => $target,
                'password' => 'SafetyMismatch_123!',
                'rememberme' => 'no',
            ];

            $response = $client->asForm()->post($loginUrl, $payload);
            if ($response->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Caught by WAF or IP Block (403) during Check', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Rate limited by Classmates (429)', mode: $this->mode(), key: $this->key());
            }

            $text = $response->body();
            if (str_contains($text, 'invalid registration/password')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (str_contains($text, 'did not find an account for the email address')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Unexpected response body structure', mode: $this->mode(), key: $this->key());
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $message = strtolower($e->getMessage());
            $reason = str_contains($message, 'timed out')
                ? 'Server took too long to respond (Read Timeout)'
                : $e->getMessage();

            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', $reason, mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Classmates uses a custom handshake flow'];
    }
}
