<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/px500.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class Px500Validator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'px500';
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
        return 'Px500';
    }

    public function siteUrl(): string
    {
        return 'https://500px.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.500px.com/graphql?query=query%28%24username%3AString%21%29%7BuserByUsername%28username%3A%24username%29%7Bid%20legacyId%20username%20displayName%20firstName%20lastName%20registeredAt%20userProfile%7Bfirstname%20lastname%20about%20country%20city%20state%7DsocialMedia%7Bwebsite%20twitter%20facebook%20instagram%7D%7D%7D&variables=%7B%22username%22%3A%22{$target}%22%7D";
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
            if (data_get($data, 'data.userByUsername') !== null) {
                return ['Taken', ''];
            }

            if (data_get($data, 'errors.0.extensions.response.status') === 404 || data_get($data, 'data.userByUsername') === null) {
                return ['Available', ''];
            }
        }

        if ($status === 404) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
