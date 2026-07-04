<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/warpcast.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class WarpcastValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'warpcast';
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
        return 'Warpcast';
    }

    public function siteUrl(): string
    {
        return 'https://warpcast.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://client.warpcast.com/v2/user-by-username?username={$target}";
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
    if (in_array($response->status(), [400, 404], true)) {
        return ['Available', ''];
    }
    if ($response->status() === 200) {
        $data = $response->json();
        if (!empty(data_get($data, 'result.user'))) {
            return ['Taken', ''];
        }
    }

    return ['Error', 'Unexpected response body, report it via GitHub issues.'];
}
}
