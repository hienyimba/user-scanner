<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/community/disqus.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class DisqusValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'disqus';
    }

    public function category(): string
    {
        return 'community';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Disqus';
    }

    public function siteUrl(): string
    {
        return 'https://disqus.com/by/{user}/';
    }

    protected function requestUrl(string $target): string
    {
        return "https://disqus.com/api/3.0/users/details?user=username%3A{$target}&attach=userFlaggedUser&api_key=E8Uh5l5fHZ6gD8U3KycjAIAk46f68Zw7C6eW8WSjZvCLXebZ7p0r1yrYDrLilk2F";
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
            $userData = data_get($data, 'response');
            if (is_array($userData) && array_key_exists('id', $userData)) {
                return ['Taken', ''];
            }
        }

        return ['Available', ''];
    }
}
