<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class SuperpornValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'superporn';
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
        return 'Superporn';
    }

    public function siteUrl(): string
    {
        return 'https://superporn.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.superporn.com/signup/check-email";
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
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'Origin' => 'https://www.superporn.com',
            'Referer' => 'https://www.superporn.com/signup',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestBody(string $target): array
    {
        return [
            'lang' => 'en_US',
            'email' => $target,
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 403) {
            return ['Error', 'Status: [403] IP blocked try using proxy or VPN'];
        }

        $data = $response->json();
        $isError = $data['error'] ?? null;
        if ($isError === true && str_contains((string) ($data['message'] ?? ''), 'Email is in use')) {
            return ['Registered', ''];
        }
        if ($isError === false && ($data['result'] ?? null) === 'ok') {
            return ['Not Registered', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues'];
    }
}
