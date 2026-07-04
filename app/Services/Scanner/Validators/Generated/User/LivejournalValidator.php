<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/livejournal.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class LivejournalValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'livejournal';
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
        return 'Livejournal';
    }

    public function siteUrl(): string
    {
        return 'https://{user}.livejournal.com';
    }

    protected function requestUrl(string $target): string
    {
        return "https://{$target}.livejournal.com";
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

    if ($status === 403) {
        return ['Error', 'HTTP 403'];
    }

    if ($status === 403 || $status === 404) {
        return ['Available', ''];
    }

    if ($status === 301 || $status === 302 || $status === 403 || $status === 410) {
        return ['Taken', ''];
    }

    if ($status === 200) {
        return ['Taken', ''];
    }

    return ['Error', 'Unexpected response body'];
}
}
