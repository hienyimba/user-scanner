<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class GumroadValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'gumroad';
    }

    public function category(): string
    {
        return 'creator';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Gumroad';
    }

    public function siteUrl(): string
    {
        return 'https://gumroad.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $showUrl = $this->siteUrl();

        try {
            $client = Http::timeout(10)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ]);

            if (!empty($options['proxy'])) {
                $client = $client->withOptions(['proxy' => $options['proxy']]);
            }

            $headers1 = [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Encoding' => 'identity',
                'sec-ch-ua' => '"Not(A:Brand";v="8", "Chromium";v="144", "Google Chrome";v="144"',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-platform' => '"Linux"',
                'upgrade-insecure-requests' => '1',
                'referer' => 'https://www.google.com/',
                'accept-language' => 'en-US,en;q=0.9',
            ];
            $res1 = $client->withHeaders($headers1)->get('https://gumroad.com/users/forgot_password/new');
            $html = $res1->body();

            $csrf = null;
            foreach ([
                '/authenticity_token&quot;:&quot;([^&]+)&quot;/',
                '/name="csrf-token" content="([^"]+)"/',
            ] as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    $csrf = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
                    break;
                }
            }

            if (!$csrf) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Failed to extract CSRF token', mode: $this->mode(), key: $this->key());
            }

            $headers2 = [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                'Accept' => 'text/html, application/xhtml+xml',
                'Accept-Encoding' => 'identity',
                'Content-Type' => 'application/json',
                'sec-ch-ua-platform' => '"Linux"',
                'x-csrf-token' => $csrf,
                'sec-ch-ua' => '"Not(A:Brand";v="8", "Chromium";v="144", "Google Chrome";v="144"',
                'x-inertia' => 'true',
                'sec-ch-ua-mobile' => '?0',
                'x-requested-with' => 'XMLHttpRequest',
                'origin' => 'https://gumroad.com',
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-dest' => 'empty',
                'referer' => 'https://gumroad.com/users/forgot_password/new',
                'accept-language' => 'en-US,en;q=0.9',
                'priority' => 'u=1, i',
            ];
            $response = $client
                ->withHeaders($headers2)
                ->withBody(json_encode(['user' => ['email' => $target]], JSON_UNESCAPED_SLASHES), 'application/json')
                ->post('https://gumroad.com/users/forgot_password');

            $data = $response->json();
            $flash = (string) data_get($data, 'props.flash.message', '');
            if (str_contains($flash, 'An account does not exist')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Registered', '', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'unexpected exception: ' . $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Gumroad uses a custom CSRF flow'];
    }
}
