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
            'PowToken' => '39bbb876b0e4f380:e80448fecdf5d1a40fcabf2e20d79c',
            'Email' => $target,
            // Deliberately invalid; this must never be a viable signup request.
            'Password' => '',
        ];
    }

    protected function timeoutSeconds(): int
    {
        return 6;
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if (!in_array($response->status(), [200, 400, 422], true)) {
            return ['Error', 'Bunny registration validation returned HTTP '.$response->status().'; account status unknown'];
        }
        $data = $response->json();
        $message = is_array($data) && is_string($data['Message'] ?? null) ? strtolower($data['Message']) : '';
        $field = is_array($data) && is_string($data['Field'] ?? null) ? strtolower($data['Field']) : '';
        if ($field === 'email' && str_contains($message, 'already in use')) {
            return ['Registered', ''];
        }
        // Password policy rejection alone does not prove that email uniqueness
        // was checked. A stale PoW token or an invalid email is not a verdict.
        return ['Error', 'Bunny did not return explicit email-duplicate evidence; account status unknown'];
    }
}
