<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class EnvatoValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'envato';
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
        return 'Envato';
    }

    public function siteUrl(): string
    {
        return 'https://elements.envato.com';
    }

    protected function requestMethod(): string { return 'POST'; }

    protected function requestUrl(string $target): string
    {
        return "https://account.envato.com/api/public/validate_email";
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
            'Content-Type' => 'application/json',
            'x-client-version' => '3.6.0',
            'origin' => 'https://elements.envato.com',
            'referer' => 'https://elements.envato.com/',
            'accept-language' => 'en-US,en;q=0.9',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestBody(string $target): array
    {
        return ['language_code' => 'en', 'email' => $target];
    }

    protected function requestBodyMode(): string
    {
        return 'json';
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() === 204) {
            return ['Not Registered', ''];
        }
        if ($response->status() === 422) {
            $data = $response->json();
            $error = strtolower((string) ($data['error_message'] ?? ''));
            if (str_contains($error, 'already in use')) {
                return ['Registered', ''];
            }
            return ['Error', 'Unexpected response body, report it via GitHub issues'];
        }
        return ['Error', 'HTTP ' . $response->status()];
    }
}
