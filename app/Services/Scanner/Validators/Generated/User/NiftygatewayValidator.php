<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/finance/niftygateway.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class NiftygatewayValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'niftygateway';
    }

    public function category(): string
    {
        return 'finance';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Niftygateway';
    }

    public function siteUrl(): string
    {
        return 'https://niftygateway.com/profile/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.niftygateway.com/user/profile-and-offchain-nifties-by-url/?profile_url={$target}";
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

        if ($status === 200) {
            $data = $response->json();
            if (data_get($data, 'didSucceed') === true && data_get($data, 'userProfileAndNifties.id') !== null) {
                return ['Taken', ''];
            }

            if (data_get($data, 'didSucceed') === true) {
                return ['Available', ''];
            }
        }

        if ($status === 400 || $status === 404 || str_contains($body, 'not_found')) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
