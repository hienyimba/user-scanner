<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;

final class HackeroneValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'hackerone';
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
        return 'Hackerone';
    }

    public function siteUrl(): string
    {
        return 'https://hackerone.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $cookieJar = new CookieJar();

        try {
            $request = Http::timeout(15)
                ->withOptions([
                    'allow_redirects' => true,
                    'cookies' => $cookieJar,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $page = $request->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'Accept-Encoding' => 'identity',
                'sec-ch-ua' => '"Not:A-Brand";v="99", "Google Chrome";v="145", "Chromium";v="145"',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-full-version' => '"145.0.7632.109"',
                'sec-ch-ua-arch' => '"x86"',
                'sec-ch-ua-platform' => '"Linux"',
                'sec-ch-ua-platform-version' => '""',
                'sec-ch-ua-model' => '""',
                'sec-ch-ua-bitness' => '"64"',
                'sec-ch-ua-full-version-list' => '"Not:A-Brand";v="99.0.0.0", "Google Chrome";v="145.0.7632.109", "Chromium";v="145.0.7632.109"',
                'upgrade-insecure-requests' => '1',
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-mode' => 'navigate',
                'sec-fetch-user' => '?1',
                'sec-fetch-dest' => 'document',
                'referer' => 'https://hackerone.com/users/sign_in',
                'accept-language' => 'en-US,en;q=0.9',
                'priority' => 'u=0, i',
            ])->get('https://hackerone.com/sign_up');

            if ($page->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF (403) during Handshake', mode: $this->mode(), key: $this->key());
            }
            if (!preg_match('/name="csrf-token" content="([^"]+)"/', $page->body(), $match)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Failed to extract CSRF token', mode: $this->mode(), key: $this->key());
            }

            $response = $request->withHeaders([
                'sec-ch-ua-full-version-list' => '"Not:A-Brand";v="99.0.0.0", "Google Chrome";v="145.0.7632.109", "Chromium";v="145.0.7632.109"',
                'sec-ch-ua-platform' => '"Linux"',
                'sec-ch-ua' => '"Not:A-Brand";v="99", "Google Chrome";v="145", "Chromium";v="145"',
                'sec-ch-ua-bitness' => '"64"',
                'sec-ch-ua-model' => '""',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-arch' => '"x86"',
                'x-requested-with' => 'XMLHttpRequest',
                'sec-ch-ua-full-version' => '"145.0.7632.109"',
                'accept' => 'application/json, text/javascript, */*; q=0.01',
                'content-type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                'x-csrf-token' => $match[1],
                'user-agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',
                'sec-ch-ua-platform-version' => '""',
                'origin' => 'https://hackerone.com',
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-dest' => 'empty',
                'referer' => 'https://hackerone.com/users/sign_up',
                'accept-encoding' => 'identity',
                'accept-language' => 'en-US,en;q=0.9',
                'priority' => 'u=1, i',
            ])->asForm()->post('https://hackerone.com/users', [
                'user[name]' => 'St33l_h3art_g3t_n0_l0v3',
                'user[username]' => 'kn0wl3dg3_is_curs3',
                'user[email]' => $target,
                'user[password]' => 'thisw0rldwasn3v3rg00d',
                'user[password_confirmation]' => 'mismatch_on_purpose',
            ]);

            if ($response->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF (403) during Validation', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited by HackerOne', mode: $this->mode(), key: $this->key());
            }

            $errors = $response->json('errors') ?? [];
            if (str_contains((string) json_encode($errors), 'has already been taken')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (!array_key_exists('email', is_array($errors) ? $errors : [])) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response: ' . $response->status(), mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
