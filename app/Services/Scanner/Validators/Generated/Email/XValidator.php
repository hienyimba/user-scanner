<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class XValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'x';
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
        return 'X';
    }

    public function siteUrl(): string
    {
        return 'https://x.com';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.x.com/i/users/email_available.json";
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
            'user-agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36',
            'accept-encoding' => 'gzip, deflate, br, zstd',
            'sec-ch-ua-platform' => '"Android"',
            'sec-ch-ua' => '"Google Chrome";v="143", "Chromium";v="143", "Not A(Brand";v="24"',
            'x-twitter-client-language' => 'en',
            'sec-ch-ua-mobile' => '?1',
            'x-twitter-active-user' => 'yes',
            'origin' => 'https://x.com',
            'priority' => 'u=1, i',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestQuery(string $target): array
    {
        return ['email' => $target];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 429) {
            return ['Error', "Rate limited wait for few minutes or use '-d' flag"];
        }

        $data = $response->json();
        $taken = $data['taken'] ?? null;
        if ($taken === true) {
            return ['Registered', ''];
        }
        if ($taken === false) {
            return ['Not Registered', ''];
        }

        return ['Error', 'Unexpected error, report it via GitHub issues'];
    }
}
