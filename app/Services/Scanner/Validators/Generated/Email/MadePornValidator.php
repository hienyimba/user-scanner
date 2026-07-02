<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class MadePornValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'made_porn';
    }

    public function category(): string
    {
        return 'adult';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'MadePorn';
    }

    public function siteUrl(): string
    {
        return 'https://made.porn';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return "https://made.porn/endpoint/api/json/change-password";
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
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Origin' => 'https://made.porn',
            'Referer' => 'https://made.porn/login',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestBody(string $target): array
    {
        return ['email' => $target];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $data = $response->json();
        if (str_contains((string) ($data['Text'] ?? ''), 'sent an email with a link')) {
            return ['Registered', ''];
        }
        if (str_contains((string) ($data['Error'] ?? ''), 'The email address is incorrect')) {
            return ['Not Registered', ''];
        }

        return ['Error', 'Unexpected response body, report it on github'];
    }
}
