<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/githubgist.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class GithubgistValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'githubgist';
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
        return 'Githubgist';
    }

    public function siteUrl(): string
    {
        return 'https://gist.github.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.github.com/users/{$target}";
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
    if ($response->status() === 404) {
        return ['Available', ''];
    }
    if ($response->status() === 403) {
        return ['Error', 'Rate limited by GitHub API'];
    }
    if ($response->status() === 200) {
        return ['Taken', ''];
    }

    return ['Error', 'Unexpected response body, report it via GitHub issues.'];
}
}
