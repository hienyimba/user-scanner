<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class NextdoorValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'nextdoor';
    }

    public function category(): string
    {
        return 'community';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Nextdoor';
    }

    public function siteUrl(): string
    {
        return 'https://nextdoor.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return "https://auth.nextdoor.com/v2/token";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 6;
    }

    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'identity',
            'sec-ch-ua-platform' => '"Android"',
            'sec-ch-ua' => '"Chromium";v="146", "Not-A.Brand";v="24", "Google Chrome";v="146"',
            'sec-ch-ua-mobile' => '?1',
            'Origin' => 'https://nextdoor.com',
            'Referer' => 'https://nextdoor.com/',
            'Priority' => 'u=1, i',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestBody(string $target): array
    {
        return [
            'scope' => 'openid',
            'client_id' => 'NEXTDOOR-WEB',
            'login_type' => 'basic',
            'grant_type' => 'password',
            'username' => $target,
            'password' => 'vhj87uyguu77',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 403) {
            return ['Error', '403'];
        }

        $data = $response->json();
        $error = (string) ($data['error'] ?? '');
        if ($error === 'invalid_grant') {
            return ['Registered', ''];
        }
        if ($error === 'not_found') {
            return ['Not Registered', ''];
        }

        return ['Error', 'Unexpected: ' . $error];
    }
}
