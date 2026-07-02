<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;

final class CodepenValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'codepen';
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
        return 'Codepen';
    }

    public function siteUrl(): string
    {
        return 'https://codepen.io';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $cookieJar = new CookieJar();

        try {
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                'Accept' => '*/*',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Referer' => 'https://codepen.io/accounts/signup/user/free',
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With' => 'XMLHttpRequest',
                'Origin' => 'https://codepen.io',
                'Accept-Encoding' => 'identity',
            ];

            $request = Http::timeout(10)
                ->withOptions([
                    'allow_redirects' => true,
                    'cookies' => $cookieJar,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $init = $request->withHeaders($headers)->get('https://codepen.io/accounts/signup/user/free');
            $csrfToken = $this->extractCsrfToken($init->body());
            if ($csrfToken !== null) {
                $headers['X-CSRF-Token'] = $csrfToken;
            }

            $response = $request->withHeaders($headers)->asForm()->post('https://codepen.io/accounts/duplicate_check', [
                'attribute' => 'email',
                'value' => $target,
                'context' => 'user',
            ]);

            if (str_contains($response->body(), 'That Email is already taken.')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() === 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response: ' . $response->status(), mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $reason = str_contains(strtolower($e->getMessage()), 'timed out') ? 'Connection timed out' : $e->getMessage();
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }

    private function extractCsrfToken(string $html): ?string
    {
        $patterns = [
            '/name=["\']csrf-token["\']\s+content=["\']([^"\']+)["\']/i',
            '/content=["\']([^"\']+)["\']\s+name=["\']csrf-token["\']/i',
            '/"csrfToken"\s*:\s*"([^"]+)"/i',
            '/"csrf_token"\s*:\s*"([^"]+)"/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                return html_entity_decode($match[1], ENT_QUOTES);
            }
        }

        return null;
    }
}
