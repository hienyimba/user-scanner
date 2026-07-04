<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/keybase.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class KeybaseValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'keybase';
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
        return 'Keybase';
    }

    public function siteUrl(): string
    {
        return 'https://keybase.io/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://keybase.io/_/api/1.0/user/lookup.json?usernames={$target}";
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
        if ($response->status() === 200) {
            $data = $response->json();
            $them = data_get($data, 'them', []);
            if (is_array($them) && ($them[0] ?? null) !== null) {
                return ['Taken', ''];
            }
            if (is_array($them)) {
                return ['Available', ''];
            }
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
