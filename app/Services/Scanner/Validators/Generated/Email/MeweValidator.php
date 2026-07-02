<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class MeweValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'mewe';
    }

    public function category(): string
    {
        return 'social';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Mewe';
    }

    public function siteUrl(): string
    {
        return 'https://mewe.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return "https://mewe.com/api/v2/auth/check/user/email";
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
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'identity',
            'Content-Type' => 'application/json; charset=UTF-8',
            'sec-ch-ua-platform' => '"Android"',
            'Origin' => 'https://mewe.com',
            'Referer' => 'https://mewe.com/register',
            'Priority' => 'u=1, i',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestBody(string $target): array
    {
        return ['email' => $target];
    }

    protected function requestBodyMode(): string
    {
        return 'json';
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 403) {
            return ['Error', 'Caught by WAF or IP Block (403)'];
        }
        if ($status === 429) {
            return ['Error', 'Rate limited by MeWe (429)'];
        }
        if ($status !== 200) {
            return ['Error', 'HTTP Error: ' . $status];
        }

        $data = $response->json();
        if (($data['exists'] ?? null) === true) {
            return ['Registered', ''];
        }
        if (($data['exists'] ?? null) === false) {
            return ['Not Registered', ''];
        }

        return ['Error', 'Unexpected response body structure'];
    }
}
