<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/email/protonmail.py
// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ProtonmailValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'protonmail';
    }

    public function category(): string
    {
        return 'email';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Protonmail';
    }

    public function siteUrl(): string
    {
        return 'https://account.proton.me';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://account.proton.me/api/core/v4/users/available';
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function requestHeaders(): array
    {
        return [
            'x-pm-appversion' => 'web-mail@6.0.1.3',
            'Accept' => 'application/json',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestQuery(string $target): array
    {
        return [
            'Name' => $target . '@proton.me',
            'ParseDomain' => '1',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();

        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        if (!in_array($status, [200, 409], true)) {
            return ['Error', '[' . $status . '] Unexpected status code from Proton'];
        }

        $data = $response->json();
        if (!is_array($data)) {
            return ['Error', 'Invalid JSON response from Proton'];
        }

        $code = $data['Code'] ?? null;
        if ($code === 12106) {
            return ['Taken', ''];
        }

        if ($code === 1000) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected Proton response code: ' . (is_scalar($code) ? (string) $code : gettype($code))];
    }
}
