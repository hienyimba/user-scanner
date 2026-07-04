<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/wordpress.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class WordpressValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'wordpress';
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
        return 'Wordpress';
    }

    public function siteUrl(): string
    {
        return 'https://profiles.wordpress.org/{user}/';
    }

    protected function requestUrl(string $target): string
    {
        return "https://profiles.wordpress.org/{$target}/";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();

        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status === 200 && preg_match('/<title>(.*?)<\/title>/is', $body, $matches) === 1) {
            if (str_contains(strtolower(trim($matches[1])), 'user profile')) {
                return ['Taken', ''];
            }
        }

        return ['Error', 'Unexpected response status: ' . $status];
    }
}
