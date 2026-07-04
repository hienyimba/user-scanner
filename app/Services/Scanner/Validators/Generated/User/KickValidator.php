<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/gaming/kick.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class KickValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'kick';
    }

    public function category(): string
    {
        return 'gaming';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Kick';
    }

    public function siteUrl(): string
    {
        return 'https://kick.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://kick.com/api/v2/channels/{$target}";
    }

    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
        ];
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

        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status !== 200) {
            return ['Error', 'Unexpected status: ' . $status];
        }

        $data = $response->json();
        if (!is_array($data)) {
            return ['Error', 'Unexpected response body'];
        }

        return ['Taken', ''];
    }
}
