<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BunnyValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'bunny';
    }

    public function category(): string
    {
        return 'hosting';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Bunny';
    }

    public function siteUrl(): string
    {
        return 'https://dash.bunny.net/';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://api.bunny.net/auth/register';
    }

    protected function requestHeadersForTarget(string $target): array
    {
        return [
            'Content-Type' => 'application/json',
            'Origin' => 'https://dash.bunny.net',
            'Referer' => 'https://dash.bunny.net/',
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
            'AffiliateCode' => '9sa3wl8vst',
            'PowToken' => '39bbb876b0e4f380:e80448fecdf5d1a40fcabf2e20d79c',
            'Email' => $target,
            'Password' => 'th3_knight_n3v3r_had_th3_st33l_h3rt_it_was_an_arm0r',
        ];
    }

    protected function timeoutSeconds(): int
    {
        return 6;
    }

    protected function parseConnectorResponse(Response $response, string $target): array
{
    if ($response->status() === 403) {
        return ['Error', 'Caught by WAF/Mitigation (403)'];
    }
    if ($response->status() === 429) {
        return ['Error', 'Rate limited (429)'];
    }
    $data = $response->json();
    $message = strtolower((string) ($data['Message'] ?? ''));
    $field = strtolower((string) ($data['Field'] ?? ''));
    if (str_contains($message, 'already in use') || $field === 'email') {
        return ['Registered', ''];
    }
    if (str_contains($message, 'passwords must have')) {
        return ['Not Registered', ''];
    }
    return ['Error', 'Unexpected response structure: ' . substr((string) ($data['Message'] ?? ''), 0, 50)];
}
}
