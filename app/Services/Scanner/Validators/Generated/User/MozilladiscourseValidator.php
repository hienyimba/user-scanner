<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/community/mozilladiscourse.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class MozilladiscourseValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'mozilladiscourse';
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
        return 'Mozilladiscourse';
    }

    public function siteUrl(): string
    {
        return 'https://discourse.mozilla.org/u/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://discourse.mozilla.org/u/{$target}.json";
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

        if ($status === 200) {
            $data = $response->json();
            if (is_array($data) && array_key_exists('user', $data)) {
                return ['Taken', ''];
            }
        }

        return ['Error', 'Unexpected response status: ' . $status];
    }
}
