<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class HackerrankValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'hackerrank';
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
        return 'Hackerrank';
    }

    public function siteUrl(): string
    {
        return 'https://www.hackerrank.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://www.hackerrank.com/auth/valid_email';
    }

    protected function requestHeadersForTarget(string $target): array
    {
        return [
            'Content-Type' => 'application/json',
            'Origin' => 'https://www.hackerrank.com',
            'Referer' => 'https://www.hackerrank.com',
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
        ];
    }

    protected function timeoutSeconds(): int
    {
        return 15;
    }

    protected function parseConnectorResponse(Response $response, string $target): array
{
    if ($response->status() === 403) {
        return ['Error', 'Caught by Cloudflare/WAF (403)'];
    }
    if ($response->status() === 429) {
        return ['Error', 'Rate limited (429)'];
    }
    if ($response->status() !== 200) {
        return ['Error', 'HTTP Error: ' . $response->status()];
    }
    $data = $response->json();
    $status = $data['status'] ?? null;
    $internal = (string) ($data['internal_status_code'] ?? '');
    $errors = strtolower((string) ($data['errors'] ?? ''));
    if ($status === false || $internal === 'already_registered' || str_contains($errors, 'already registered')) {
        return ['Registered', ''];
    }
    if ($status === true) {
        return ['Not Registered', ''];
    }
    return ['Error', 'Unexpected response payload schema'];
}
}
