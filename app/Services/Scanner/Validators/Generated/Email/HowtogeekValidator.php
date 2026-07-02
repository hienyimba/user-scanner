<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class HowtogeekValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'howtogeek';
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
        return 'Howtogeek';
    }

    public function siteUrl(): string
    {
        return 'https://www.howtogeek.com';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.howtogeek.com/check-user-exists/";
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
            'sec-ch-ua-platform' => '"Android"',
            'Referer' => 'https://www.howtogeek.com/',
            'Priority' => 'u=1, i',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestQuery(string $target): array
    {
        return ['email' => $target];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() === 403) return ['Error', 'Caught by WAF or IP Block (403)'];
        if ($response->status() === 429) return ['Error', 'Rate limited by How-To Geek (429)'];
        if ($response->status() !== 200) return ['Error', 'HTTP Error: ' . $response->status()];
        $data = $response->json();
        if (($data['userExists'] ?? null) === true) return ['Registered', ''];
        if (($data['userExists'] ?? null) === false) return ['Not Registered', ''];
        return ['Error', 'Unexpected response body structure'];
    }
}
