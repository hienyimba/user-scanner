<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/hashicorp_discuss.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class HashicorpDiscussValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'hashicorp_discuss';
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
        return 'HashicorpDiscuss';
    }

    public function siteUrl(): string
    {
        return 'https://discuss.hashicorp.com/u/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://discuss.hashicorp.com/u/{$target}.json";
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
        return $this->parseDiscourseProfileResponse($response);
    }
}
