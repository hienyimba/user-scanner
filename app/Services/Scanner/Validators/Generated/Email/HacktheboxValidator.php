<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;

final class HacktheboxValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'hackthebox';
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
        return 'Hackthebox';
    }

    public function siteUrl(): string
    {
        return 'https://hackthebox.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $cookieJar = new CookieJar();

        try {
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
                'Accept' => 'application/json',
                'Accept-Encoding' => 'identity',
                'Content-Type' => 'application/json',
                'sec-ch-ua-platform' => '"Android"',
                'sec-ch-ua' => '"Not:A-Brand";v="99", "Google Chrome";v="145", "Chromium";v="145"',
                'sec-ch-ua-mobile' => '?1',
                'x-requested-with' => 'XMLHttpRequest',
                'origin' => 'https://account.hackthebox.com',
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-dest' => 'empty',
                'referer' => 'https://account.hackthebox.com/register',
                'accept-language' => 'en-US,en;q=0.9',
                'priority' => 'u=1, i',
            ];

            $request = Http::timeout(15)->withOptions([
                'allow_redirects' => true,
                'cookies' => $cookieJar,
                'verify' => (bool) config('scanner.verify_ssl', false),
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $init = $request->withHeaders(['User-Agent' => $headers['User-Agent']])->get('https://account.hackthebox.com/register');
            if ($init->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF or IP Block (403) during Handshake', mode: $this->mode(), key: $this->key());
            }

            $xsrfToken = null;
            foreach ($cookieJar->toArray() as $cookie) {
                if (($cookie['Name'] ?? null) === 'XSRF-TOKEN') {
                    $xsrfToken = urldecode((string) ($cookie['Value'] ?? ''));
                    break;
                }
            }

            if ($xsrfToken === null || $xsrfToken === '') {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Failed to extract XSRF-TOKEN from HackTheBox', mode: $this->mode(), key: $this->key());
            }

            $headers['x-xsrf-token'] = $xsrfToken;
            $response = $request->withHeaders($headers)
                ->withBody(json_encode(['email' => $target], JSON_THROW_ON_ERROR), 'application/json')
                ->post('https://account.hackthebox.com/api/v1/user/email/verify');

            $status = $response->status();
            if ($status === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF or IP Block (403) during Validation', mode: $this->mode(), key: $this->key());
            }
            if ($status === 422) {
                if (str_contains((string) $response->body(), 'cannot use this email address')) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
                }
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Validation error: ' . (string) $response->json('message'), mode: $this->mode(), key: $this->key());
            }
            if (in_array($status, [200, 204], true)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }
            if ($status === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited by HackTheBox', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected status code: ' . $status, mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $reason = str_contains(strtolower($e->getMessage()), 'timed out') ? 'Connection timed out! maybe region blocks' : $e->getMessage();
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
