<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class DevrantValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'devrant';
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
        return 'Devrant';
    }

    public function siteUrl(): string
    {
        return 'https://devrant.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return "https://devrant.com/api/users";
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
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With' => 'XMLHttpRequest',
            'Origin' => 'https://devrant.com',
            'Referer' => 'https://devrant.com/feed/top/month?login=1',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestBody(string $target): array
    {
        return [
            'app' => '3',
            'type' => '1',
            'email' => $target,
            'username' => '',
            'password' => '',
            'guid' => '',
            'plat' => '3',
            'sid' => '',
            'seid' => '',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() !== 200) {
            return ['Error', 'Unexpected status code: ' . $response->status()];
        }
        $data = $response->json();
        $error = (string) ($data['error'] ?? '');
        if ($error === 'The email specified is already registered to an account.') {
            return ['Registered', ''];
        }
        return ['Not Registered', ''];
    }
}
