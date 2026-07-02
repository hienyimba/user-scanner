<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class XdaValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'xda';
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
        return 'Xda';
    }

    public function siteUrl(): string
    {
        return 'https://xda-developers.com';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.xda-developers.com/check-user-exists/";
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
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36',
            'Accept' => 'application/json',
            'Referer' => 'https://www.xda-developers.com/',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestQuery(string $target): array
    {
        return ['email' => $target, 'subscribe' => 'true'];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $data = $response->json();
        $exists = $data['userExists'] ?? null;
        if ($exists === true) return ['Registered', ''];
        if ($exists === false) return ['Not Registered', ''];
        return ['Error', 'Unexpected response body, report it on github'];
    }
}
