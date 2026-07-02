<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class PlurkValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'plurk';
    }

    public function category(): string
    {
        return 'social';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Plurk';
    }

    public function siteUrl(): string
    {
        return 'https://www.plurk.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.plurk.com/Users/isEmailFound";
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
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'Accept' => '*/*',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With' => 'XMLHttpRequest',
            'Origin' => 'https://www.plurk.com',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestBody(string $target): array
    {
        return ['email' => $target];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 403) {
            return ['Error', 'Caught by WAF or IP Block (403)'];
        }
        if ($status === 429) {
            return ['Error', 'Rate limited (429)'];
        }
        if ($status !== 200) {
            return ['Error', 'HTTP Error: ' . $status];
        }

        $text = trim($response->body());
        if ($text === 'True') {
            return ['Registered', ''];
        }
        if ($text === 'False') {
            return ['Not Registered', ''];
        }

        return ['Error', 'Unexpected response body structure'];
    }
}
