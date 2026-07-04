<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/creator/fansly.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class FanslyValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'fansly';
    }

    public function category(): string
    {
        return 'creator';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Fansly';
    }

    public function siteUrl(): string
    {
        return 'https://fansly.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://apiv2.fansly.com/api/v1/account?usernames={$target}";
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
            $entries = data_get($data, 'response', []);
            if (data_get($data, 'success') === true && is_array($entries) && $entries !== []) {
                return ['Taken', ''];
            }

            if (data_get($data, 'success') === true && is_array($entries)) {
                return ['Available', ''];
            }
        }

        if ($status === 404) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
