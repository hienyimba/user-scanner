<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/other/duolingo.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class DuolingoValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'duolingo';
    }

    public function category(): string
    {
        return 'other';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Duolingo';
    }

    public function siteUrl(): string
    {
        return 'https://www.duolingo.com/profile/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.duolingo.com/2017-06-30/users?username={$target}";
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

        if ($status === 200) {
            $users = data_get($response->json(), 'users', []);
            if (is_array($users) && $users !== []) {
                return ['Taken', ''];
            }

            if (is_array($users)) {
                return ['Available', ''];
            }
        }

        if ($status === 404) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
