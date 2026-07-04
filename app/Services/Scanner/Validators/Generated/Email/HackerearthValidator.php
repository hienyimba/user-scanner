<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class HackerearthValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'hackerearth';
    }

    public function category(): string
    {
        return 'dev';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Hackerearth';
    }

    public function siteUrl(): string
    {
        return 'https://www.hackerearth.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://www.hackerearth.com/api/v1/sparta/auth/signup/';
    }

    protected function requestHeadersForTarget(string $target): array
    {
        return [
            'Content-Type' => 'application/json',
            'Origin' => 'https://www.hackerearth.com',
            'Referer' => 'https://www.hackerearth.com',
        ];
    }

    protected function requestQuery(string $target): array
    {
        return [
            'sxhr' => true,
            'next' => '/community/dashboard/',
        ];
    }

    protected function requestBodyMode(): string
    {
        return 'json';
    }

    protected function requestBody(string $target): array
    {
        return [
            'first_name' => 'Hunan',
            'last_name' => 'Fish',
            'email' => $target,
            'password' => '',
            'policy_accepted' => true,
            'next' => '/community/dashboard/',
        ];
    }

    protected function timeoutSeconds(): int
    {
        return 15;
    }

    protected function parseConnectorResponse(Response $response, string $target): array
{
    if ($response->status() === 403) {
        return ['Error', 'Caught by WAF (403)'];
    }
    if ($response->status() === 429) {
        return ['Error', 'Rate limited (429)'];
    }
    $errors = (array) (data_get($response->json(), 'errors', []));
    $emailError = strtolower((string) ($errors['email'] ?? ''));
    if (str_contains($emailError, 'already registered')) {
        return ['Registered', ''];
    }
    if (array_key_exists('password', $errors) && $emailError === '') {
        return ['Not Registered', ''];
    }
    return ['Error', 'Unexpected response field layout'];
}
}
