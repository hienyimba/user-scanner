<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/zhihu.py
// parity-class: generated

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ZhihuValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'zhihu';
    }

    public function category(): string
    {
        return 'social';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Zhihu';
    }

    public function siteUrl(): string
    {
        return 'https://www.zhihu.com/people/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.zhihu.com/books/people/{$target}/publications?offset=0&limit=5";
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
        $status = $response->status();
        $body = $response->body();
        $finalUrl = (string) ($response->effectiveUri() ?? '');

        if ($status === 200 && str_contains($body, 'is_start')) {
            return ['Taken', ''];
        }

        if (str_contains($body, 'NotFoundException') || $status === 404) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body'];
    }
}
