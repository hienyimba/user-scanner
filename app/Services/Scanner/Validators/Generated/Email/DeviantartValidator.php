<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Support\Facades\Http;

final class DeviantartValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'deviantart';
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
        return 'Deviantart';
    }

    public function siteUrl(): string
    {
        return 'https://www.deviantart.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(10)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
                'allow_redirects' => true,
                'version' => 1.1,
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Encoding' => 'identity',
                'sec-ch-ua-platform' => '"Android"',
                'upgrade-insecure-requests' => '1',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $home = $request->get('https://www.deviantart.com');
            if ($home->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF or IP Block (403) during Handshake 1', mode: $this->mode(), key: $this->key());
            }

            $join = $request->get('https://www.deviantart.com/join/');
            if ($join->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF or IP Block (403) during Handshake 2', mode: $this->mode(), key: $this->key());
            }

            if (!preg_match("/window\\.__CSRF_TOKEN__\\s*=\\s*'([^']+)'/", $join->body(), $match)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Failed to extract CSRF token from window variable', mode: $this->mode(), key: $this->key());
            }

            $csrfToken = $match[1];

            $response = $request->withHeaders([
                'origin' => 'https://www.deviantart.com',
                'referer' => 'https://www.deviantart.com/join/',
            ])->asForm()->post('https://www.deviantart.com/_sisu/do/signup2', [
                'referer' => 'https://www.deviantart.com/',
                'referer_type' => '',
                'csrf_token' => $csrfToken,
                'join_mode' => 'email',
                'oauth' => '0',
                'email' => $target,
                'password' => '',
                'username' => 'scanner_test_99',
                'dobMonth' => '6',
                'dobDay' => '6',
                'dobYear' => '1998',
            ]);

            if ($response->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF or IP Block (403) during Validation', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited by DeviantArt', mode: $this->mode(), key: $this->key());
            }

            $body = $response->body();
            if (str_contains($body, 'id="email-error">That email address is already in use.')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (!str_contains($body, 'id="email-error"')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response content or status: ' . $response->status(), mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = match (true) {
                str_contains($message, 'timed out') => 'Connection timed out! maybe region blocks',
                str_contains($message, 'unexpected eof while reading') => 'TLS connection closed unexpectedly by DeviantArt; likely transport-level blocking',
                default => $e->getMessage(),
            };

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }
}
