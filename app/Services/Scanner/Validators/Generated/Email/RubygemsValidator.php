<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;

final class RubygemsValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'rubygems'; }
    public function category(): string { return 'dev'; }
    public function mode(): string { return 'email'; }
    public function siteName(): string { return 'Rubygems'; }
    public function siteUrl(): string { return 'https://rubygems.org'; }

    public function check(string $target, array $options = []): ScanResult
    {
        $cookieJar = new CookieJar();

        try {
            $request = Http::timeout(15)->withOptions([
                'allow_redirects' => true,
                'verify' => (bool) config('scanner.verify_ssl', false),
                'cookies' => $cookieJar,
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br, zstd',
                'sec-ch-ua' => '"Brave";v="149", "Chromium";v="149", "Not)A;Brand";v="24"',
                'sec-ch-ua-mobile' => '?1',
                'sec-ch-ua-platform' => '"Android"',
                'upgrade-insecure-requests' => '1',
                'sec-gpc' => '1',
                'accept-language' => 'en-US,en;q=0.7',
                'origin' => 'https://rubygems.org',
                'referer' => 'https://rubygems.org/sign_up',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $init = $request->get('https://rubygems.org/sign_up');
            if ($init->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Failed to grab verification context: ' . $init->status(), mode: $this->mode(), key: $this->key());
            }
            if (!preg_match('/name="authenticity_token" value="([^"]+)"/', $init->body(), $match)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Could not scrape Rails authenticity token', mode: $this->mode(), key: $this->key());
            }

            $response = $request->asForm()->post('https://rubygems.org/users', [
                'authenticity_token' => $match[1],
                'user[full_name]' => '',
                'user[email]' => $target,
                'user[handle]' => '',
                'user[password]' => '',
                'user[public_email]' => '0',
                'commit' => 'Sign up',
            ]);
            $body = $response->body();

            if (str_contains($body, 'has already been taken')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', mode: $this->mode(), key: $this->key());
            }
            if (str_contains($body, 'prohibited this user from being saved') || str_contains($body, "Password can't be blank")) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected signature inside response DOM tree', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }
}
