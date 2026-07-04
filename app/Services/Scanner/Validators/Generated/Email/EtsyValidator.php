<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class EtsyValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'etsy';
    }

    public function category(): string
    {
        return 'shopping';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Etsy';
    }

    public function siteUrl(): string
    {
        return 'https://www.etsy.com';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://www.etsy.com/api/v3/ajax/public/users/by-identity-optional';
    }

    protected function requestHeadersForTarget(string $target): array
    {
        return [
            'Referer' => 'https://www.etsy.com/join/email',
        ];
    }

    protected function requestQuery(string $target): array
    {
        return [
            'identity' => $target,
        ];
    }

    protected function requestBodyMode(): string
    {
        return 'form';
    }

    protected function requestBody(string $target): array
    {
        return [];
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function parseConnectorResponse(Response $response, string $target): array
{
    if ($response->status() === 403) {
        return ['Error', '403'];
    }
    if (trim($response->body()) === 'null') {
        return ['Not Registered', ''];
    }
    if (!empty(($response->json())['user_id'] ?? null)) {
        return ['Registered', ''];
    }
    return ['Error', 'Unexpected response body structure'];
}
}
