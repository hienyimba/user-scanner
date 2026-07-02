<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class WordpressValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'wordpress';
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
        return 'Wordpress';
    }

    public function siteUrl(): string
    {
        return 'https://wordpress.com';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://public-api.wordpress.com/rest/v1.1/users/{$target}/auth-options";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 5;
    }

    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
            'sec-ch-ua-platform' => '"Linux"',
            'sec-ch-ua' => '"Not(A:Brand";v="8", "Chromium";v="144", "Google Chrome";v="144"',
            'sec-ch-ua-mobile' => '?0',
            'sec-fetch-site' => 'same-origin',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-dest' => 'empty',
            'referer' => 'https://public-api.wordpress.com/wp-admin/rest-proxy/?v=2.0',
            'accept-language' => 'en-US,en;q=0.9',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestQuery(string $target): array
    {
        return ['http_envelope' => '1'];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() !== 200) {
            return ['Error', 'WordPress API returned status ' . $response->status()];
        }
        $data = $response->json();
        $code = $data['code'] ?? null;
        $body = is_array($data['body'] ?? null) ? $data['body'] : [];
        if ($code === 200) return ['Registered', ''];
        if ($code === 404 && (($body['error'] ?? null) === 'unknown_user')) return ['Not Registered', ''];
        if (str_contains((string) ($body['message'] ?? ''), 'Please log in using your WordPress.com username instead of your email address.')) {
            return ['Registered', ''];
        }
        return ['Error', 'WordPress Error: ' . ($body['message'] ?? 'Unknown API response')];
    }
}
