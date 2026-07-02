<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class StackoverflowValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'stackoverflow';
    }

    public function category(): string
    {
        return 'community';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Stackoverflow';
    }

    public function siteUrl(): string
    {
        return 'https://stackoverflow.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return "https://stackoverflow.com/users/login";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Encoding' => 'identity',
            'sec-ch-ua-platform' => '"Linux"',
            'origin' => 'https://stackoverflow.com',
            'referer' => 'https://stackoverflow.com/users/login',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestBody(string $target): array
    {
        return [
            'ssrc' => 'login',
            'email' => $target,
            'password' => 'Password109-grt',
            'oauth_version' => '',
            'oauth_server' => '',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $body = $response->body();
        if (str_contains($body, 'No user found with matching email')) {
            return ['Not Registered', ''];
        }
        if (str_contains($body, 'The email or password is incorrect')) {
            return ['Registered', ''];
        }

        return ['Error', 'Unexpected response body'];
    }
}
