<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class HubspotValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'hubspot';
    }

    public function category(): string
    {
        return 'crm';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Hubspot';
    }

    public function siteUrl(): string
    {
        return 'https://hubspot.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.hubspot.com/login-api/v1/login";
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
            'authority' => 'api.hubspot.com',
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
            'content-type' => 'application/json',
            'origin' => 'https://app.hubspot.com',
            'referer' => 'https://app.hubspot.com/',
            'accept-language' => 'en-US,en;q=0.9',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestBody(string $target): array
    {
        return [
            'email' => $target,
            'password' => '',
            'rememberLogin' => false,
        ];
    }

    protected function requestBodyMode(): string
    {
        return 'json';
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 400) {
            $data = $response->json();
            $state = $data['status'] ?? null;
            if ($state === 'INVALID_PASSWORD') {
                return ['Registered', ''];
            }
            if ($state === 'INVALID_USER') {
                return ['Not Registered', ''];
            }

            return ['Error', ''];
        }

        return ['Error', 'HTTP ' . $status];
    }
}
