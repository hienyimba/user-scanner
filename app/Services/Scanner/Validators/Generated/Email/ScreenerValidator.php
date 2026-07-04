<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;

final class ScreenerValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'screener'; }
    public function category(): string { return 'other'; }
    public function mode(): string { return 'email'; }
    public function siteName(): string { return 'Screener'; }
    public function siteUrl(): string { return 'https://www.screener.in'; }

    public function check(string $target, array $options = []): ScanResult
    {
        $cookieJar = new CookieJar();

        try {
            $request = Http::timeout(6)->withOptions([
                'allow_redirects' => true,
                'verify' => (bool) config('scanner.verify_ssl', false),
                'cookies' => $cookieJar,
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'sec-ch-ua-platform' => '"Android"',
                'sec-ch-ua' => '"Brave";v="147", "Not.A/Brand";v="8", "Chromium";v="147"',
                'sec-ch-ua-mobile' => '?1',
                'Upgrade-Insecure-Requests' => '1',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $init = $request->get('https://www.screener.in/register/');
            if ($init->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF or IP Block (403)', mode: $this->mode(), key: $this->key());
            }
            if ($init->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited by Screener (429)', mode: $this->mode(), key: $this->key());
            }
            if ($init->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'HTTP Error: ' . $init->status(), mode: $this->mode(), key: $this->key());
            }
            if (!preg_match('/name="csrfmiddlewaretoken" value="([^"]+)"/', $init->body(), $csrf) || !preg_match('/name="token" value="([^"]+)"/', $init->body(), $token)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response body structure, report it via GitHub issues', mode: $this->mode(), key: $this->key());
            }

            $response = $request->withHeaders([
                'Origin' => 'https://www.screener.in',
                'Referer' => 'https://www.screener.in/register/',
            ])->asForm()->post('https://www.screener.in/register/', [
                'csrfmiddlewaretoken' => $csrf[1],
                'next' => '',
                'token' => $token[1],
                'email' => $target,
                'email2' => $target,
                'password' => '',
            ]);

            if ($response->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF or IP Block (403)', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited by Screener (429)', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'HTTP Error: ' . $response->status(), mode: $this->mode(), key: $this->key());
            }

            $body = $response->body();
            if (str_contains($body, 'User account with this Email already exists')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', mode: $this->mode(), key: $this->key());
            }
            if (str_contains($body, '<ul class="errorlist"><li>This field is required.</li>')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response body structure, report it via GitHub issues', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }
}
