<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;

final class GithubValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'github';
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
        return 'Github';
    }

    public function siteUrl(): string
    {
        return 'https://github.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $cookieJar = new CookieJar();

        try {
            $request = Http::timeout(10)
                ->withOptions([
                    'allow_redirects' => true,
                    'cookies' => $cookieJar,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $signup = $request->withHeaders([
                'host' => 'github.com',
                'sec-ch-ua' => '"Not(A:Brand";v="8", "Chromium";v="144", "Google Chrome";v="144"',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-platform' => '"Linux"',
                'upgrade-insecure-requests' => '1',
                'user-agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'sec-fetch-site' => 'cross-site',
                'sec-fetch-mode' => 'navigate',
                'sec-fetch-user' => '?1',
                'sec-fetch-dest' => 'document',
                'referer' => 'https://www.google.com/',
                'accept-encoding' => 'identity',
                'accept-language' => 'en-US,en;q=0.9',
                'priority' => 'u=0, i',
            ])->get('https://github.com/signup');

            if (!preg_match('/data-csrf="true"\s+value="([^"]+)"/', $signup->body(), $match)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Failed to extract GitHub authenticity_token', mode: $this->mode(), key: $this->key());
            }

            $response = $request->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                'Accept-Encoding' => 'identity',
                'sec-ch-ua-platform' => '"Linux"',
                'sec-ch-ua' => '"Not(A:Brand";v="8", "Chromium";v="144", "Google Chrome";v="144"',
                'sec-ch-ua-mobile' => '?0',
                'origin' => 'https://github.com',
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-dest' => 'empty',
                'referer' => 'https://github.com/signup',
                'accept-language' => 'en-US,en;q=0.9',
                'priority' => 'u=1, i',
            ])->asForm()->post('https://github.com/email_validity_checks', [
                'authenticity_token' => $match[1],
                'value' => $target,
            ]);

            $body = $response->body();
            if (str_contains($body, 'already associated with an account')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() === 200 && str_contains($body, 'Email is available')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected status code: ' . $response->status() . ', report this via GitHub issues', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'unexpected exception: ' . $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
