<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BuymeacoffeeValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'buymeacoffee';
    }

    public function category(): string
    {
        return 'creator';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Buymeacoffee';
    }

    public function siteUrl(): string
    {
        return 'https://www.buymeacoffee.com/';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://app.buymeacoffee.com/api/v1/email/login';
    }

    protected function requestHeadersForTarget(string $target): array
    {
        return [
            'Content-Type' => 'application/json',
            'x-device-fingerprint' => bin2hex(random_bytes(10)),
        ];
    }

    protected function requestQuery(string $target): array
    {
        return [];
    }

    protected function requestBodyMode(): string
    {
        return 'json';
    }

    protected function requestBody(string $target): array
    {
        return [
            'email' => $target,
            'client_response' => '',
            'captcha_version' => 'v3',
        ];
    }

    protected function timeoutSeconds(): int
    {
        return 7;
    }

    protected function parseConnectorResponse(Response $response, string $target): array
{
    if ($response->status() === 403) {
        return ['Error', 'Caught by Cloudflare/WAF (403)'];
    }
    if ($response->status() === 429) {
        return ['Error', 'Rate limited by BuyMeaCoffee (429)'];
    }
    $data = $response->json();
    $message = strtolower((string) ($data['message'] ?? ''));
    if ($response->status() === 200 && ($data['otp_login'] ?? null) === true) {
        return ['Registered', ''];
    }
    if ($response->status() === 422 || str_contains($message, 'no account with the given')) {
        return ['Not Registered', ''];
    }
    foreach ((array) data_get($data, 'errors.email', []) as $error) {
        if (str_contains(strtolower((string) $error), 'no account')) {
            return ['Not Registered', ''];
        }
    }
    return ['Error', 'Unexpected API State (HTTP ' . $response->status() . ')'];
}
}
