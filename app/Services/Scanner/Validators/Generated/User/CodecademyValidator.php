<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/codecademy.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class CodecademyValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'codecademy';
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
        return 'Codecademy';
    }

    public function siteUrl(): string
    {
        return 'https://www.codecademy.com/profiles/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.codecademy.com/profiles/{$target}";
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

        if ($status !== 200) {
            return ['Error', 'Unexpected response body, report it via GitHub issues.'];
        }

        if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $body, $matches) === 1) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded)) {
                $profile = $decoded['props']['pageProps']['profile'] ?? null;
                if (is_array($profile) && (($profile['type'] ?? null) === 'UserNotFound' || ($profile['__typename'] ?? null) === 'UserNotFound')) {
                    return ['Available', ''];
                }
            }
        }

        return ['Taken', ''];
    }
}
