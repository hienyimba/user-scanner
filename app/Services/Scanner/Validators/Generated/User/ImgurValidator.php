<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/imgur.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ImgurValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'imgur';
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
        return 'Imgur';
    }

    public function siteUrl(): string
    {
        return 'https://imgur.com/user/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.imgur.com/account/v1/accounts/{$target}?client_id=546c25a59c58ad7&include=trophies%2Cmedallions";
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

        if ($status === 200 && data_get($response->json(), 'id') !== null) {
            return ['Taken', ''];
        }

        if ($status === 400 || $status === 404) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
