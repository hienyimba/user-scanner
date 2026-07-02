<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;

final class QiitaValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'qiita';
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
        return 'Qiita';
    }

    public function siteUrl(): string
    {
        return 'https://qiita.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $cookieJar = new CookieJar();

        try {
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Encoding' => 'identity',
                'sec-ch-ua-platform' => '"Android"',
                'sec-ch-ua' => '"Not:A-Brand";v="99", "Google Chrome";v="145", "Chromium";v="145"',
                'sec-ch-ua-mobile' => '?1',
                'origin' => 'https://qiita.com',
                'referer' => 'https://qiita.com/signup?callback_action=login_or_signup&redirect_to=%2F&realm=qiita',
                'accept-language' => 'en-US,en;q=0.9',
            ];

            $request = Http::timeout(15)->withOptions([
                'allow_redirects' => true,
                'cookies' => $cookieJar,
                'verify' => (bool) config('scanner.verify_ssl', false),
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $request->withHeaders($headers)->get('https://qiita.com/privacy');
            $signup = $request->withHeaders($headers)->get('https://qiita.com/signup?callback_action=login_or_signup&redirect_to=%2F&realm=qiita');

            if (!preg_match('/name="csrf-token" content="([^"]+)"/', $signup->body(), $match)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Failed to extract CSRF token', mode: $this->mode(), key: $this->key());
            }

            $response = $request->withHeaders($headers)->asForm()->post('https://qiita.com/registration', [
                'authenticity_token' => $match[1],
                'user[url_name]' => 'scanner_check_99',
                'user[email]' => $target,
                'user[password]' => 'SafetyMismatch_123!',
                'g-recaptcha-response' => '',
                'commit' => 'register',
                'redirect_to' => '/',
            ]);

            if (str_contains($response->body(), 'has already been taken')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
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
