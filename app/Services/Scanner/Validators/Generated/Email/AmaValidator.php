<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class AmaValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'ama';
    }

    public function category(): string
    {
        return 'other';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Ama';
    }

    public function siteUrl(): string
    {
        return 'https://www.ama-assn.org';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://fsso.ama-assn.org/api/resetPassword';
    }

    protected function requestHeadersForTarget(string $target): array
    {
        return [
            'Content-Type' => 'application/json',
            'Origin' => 'https://fsso.ama-assn.org',
            'Referer' => 'https://www.ama-assn.org',
            'Cookie' => 'IV_JCT=%2Flogin;',
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
            'emailAddressOrPhone' => $target,
            'fedType' => 'oAuth',
            'returnUrl' => '/AMAresources',
            'refererUrl' => '/AMAresources',
            'successUrl' => 'https://www.ama-assn.org/',
            'appCxUrl' => 'https://www.ama-assn.org/',
        ];
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function parseConnectorResponse(Response $response, string $target): array
{
    if ($response->status() === 403) {
        return ['Error', 'Caught by WAF (403)'];
    }
    if ($response->status() === 429) {
        return ['Error', 'Rate limited (429)'];
    }
    $data = $response->json();
    $message = strtolower((string) ($data['message'] ?? ''));
    $code = (string) ($data['httpCode'] ?? '');
    if ($code === '200' && str_contains($message, 'sent successfully')) {
        return ['Registered', ''];
    }
    if ($code === '202' || str_contains($message, 'not found') || str_contains($message, 'please use an existing account')) {
        return ['Not Registered', ''];
    }
    return ['Error', 'Logic Mismatch - Code: ' . $code . ' | Msg: ' . substr((string) ($data['message'] ?? ''), 0, 50)];
}
}
