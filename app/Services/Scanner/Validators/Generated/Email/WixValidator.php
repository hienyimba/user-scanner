<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class WixValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'wix';
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
        return 'Wix';
    }

    public function siteUrl(): string
    {
        return 'https://wix.com';
    }

    protected function requestMethod(): string { return 'POST'; }

    protected function requestUrl(string $target): string
    {
        return "https://users.wix.com/wix-users/v1/userAccountsByEmail";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 7;
    }

    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'identity',
            'Content-Type' => 'application/json',
            'sec-ch-ua-platform' => '"Android"',
            'x-wix-client-artifact-id' => 'login-react-app',
            'origin' => 'https://users.wix.com',
            'referer' => 'https://users.wix.com/signin/signup',
            'accept-language' => 'en-US,en;q=0.9',
            'priority' => 'u=1, i',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestBody(string $target): array
    {
        return ['email' => $target];
    }

    protected function requestBodyMode(): string
    {
        return 'json';
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() === 200) {
            $data = $response->json();
            if (isset($data['accountsData']) && is_array($data['accountsData']) && count($data['accountsData']) > 0) {
                return ['Registered', ''];
            }
            return ['Error', 'Unexpected response body structure, report it via GitHub issues'];
        }
        if ($response->status() === 404) return ['Not Registered', ''];
        if ($response->status() === 429) return ['Error', 'Rate limited wait for few minutes'];
        return ['Error', 'Unexpected status code: ' . $response->status() . ', report it via GitHub issues'];
    }
}
