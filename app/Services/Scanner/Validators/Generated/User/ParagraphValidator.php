<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/other/paragraph.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ParagraphValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'paragraph';
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
        return 'Paragraph';
    }

    public function siteUrl(): string
    {
        return 'https://paragraph.com/@{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://paragraph.com/api/blogs/@{$target}";
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
            if (is_array($data) && array_key_exists('id', $data)) {
                return ['Taken', ''];
            }
        }

        return ['Available', ''];
    }
}
