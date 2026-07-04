<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/codeforces.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class CodeforcesValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'codeforces';
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
        return 'Codeforces';
    }

    public function siteUrl(): string
    {
        return 'https://codeforces.com/profile/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://codeforces.com/api/user.info?handles={$target}";
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
            $data = $response->json();
            if (data_get($data, 'status') === 'OK' && data_get($data, 'result.0') !== null) {
                return ['Taken', ''];
            }
        }

        if ($status === 400 || $status === 404) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
