<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/community/wikipedia.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class WikipediaValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'wikipedia';
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
        return 'Wikipedia';
    }

    public function siteUrl(): string
    {
        return 'https://en.wikipedia.org/wiki/User:{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://en.wikipedia.org/w/api.php?action=query&format=json&list=users&ususers={$target}&usprop=editcount|registration|gender&formatversion=2";
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
    if ($response->status() !== 200) {
        return ['Error', 'Unexpected status: ' . $response->status()];
    }

    $data = $response->json();
    $users = data_get($data, 'query.users', []);
    if (!is_array($users) || $users === []) {
        return ['Error', 'Invalid API response format'];
    }

    $userData = $users[0] ?? [];
    if (is_array($userData) && array_key_exists('missing', $userData)) {
        return ['Available', ''];
    }

    return ['Taken', ''];
}
}
