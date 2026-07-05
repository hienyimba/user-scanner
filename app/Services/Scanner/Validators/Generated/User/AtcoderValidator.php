<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/atcoder.py
// parity-class: generated

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class AtcoderValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'atcoder';
    }

    public function category(): string
    {
        return 'dev';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Atcoder';
    }

    public function siteUrl(): string
    {
        return 'https://atcoder.jp/users/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://atcoder.jp/api/users/exists/?userScreenName={$target}";
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
        return [];
    }

    protected function requestQuery(string $target): array
    {
        return [];
    }


    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $body = $response->body();
        $normalizedBody = strtolower(trim($body));

        if ($normalizedBody === 'true') {
            return ['Taken', ''];
        }

        if ($normalizedBody === 'false') {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body'];
    }
}
