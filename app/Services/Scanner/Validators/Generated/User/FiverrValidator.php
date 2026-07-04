<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/shopping/fiverr.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class FiverrValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'fiverr';
    }

    public function category(): string
    {
        return 'shopping';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Fiverr';
    }

    public function siteUrl(): string
    {
        return 'https://www.fiverr.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.fiverr.com/{$target}";
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
        $finalUrl = (string) ($response->effectiveUri() ?? '');

        if ($status === 200 && $finalUrl === '') {
            return ['Taken', ''];
        }

        if ($status === 200 && $finalUrl !== 'https://www.fiverr.com/' && !str_contains($finalUrl, '/errors/')) {
            return ['Taken', ''];
        }

        if ($status === 404 || $finalUrl === 'https://www.fiverr.com/' || str_contains($finalUrl, '/errors/')) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response status: ' . $status];
    }
}
