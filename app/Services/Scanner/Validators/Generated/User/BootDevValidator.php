<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/boot_dev.py
// parity-class: generated

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BootDevValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'boot_dev';
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
        return 'BootDev';
    }

    public function siteUrl(): string
    {
        return 'https://boot.dev/u/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.boot.dev/u/{$target}";
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

        if ($status === 404) {
            return ['Available', ''];
        }

        if (str_contains($body, 'User not found')) {
            return ['Available', ''];
        }

        if ($status === 200 && str_contains($body, '__NUXT__') && str_contains($body, 'publicUser:' . $target)) {
            return ['Taken', ''];
        }

        return ['Error', 'Unexpected response body'];
    }
}
