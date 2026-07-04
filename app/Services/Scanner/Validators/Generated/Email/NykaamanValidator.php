<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class NykaamanValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'nykaaman';
    }

    public function category(): string
    {
        return 'shopping';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Nykaaman';
    }

    public function siteUrl(): string
    {
        return 'https://www.nykaaman.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://www.nykaaman.com/app-api/index.php/customer/check_existence';
    }

    protected function requestHeadersForTarget(string $target): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'identity',
            'sec-ch-ua-platform' => '"Android"',
            'sec-ch-ua' => '"Brave";v="149", "Chromium";v="149", "Not)A;Brand";v="24"',
            'sec-ch-ua-mobile' => '?1',
            'sec-gpc' => '1',
            'accept-language' => 'en-US,en;q=0.9',
            'Origin' => 'https://www.nykaaman.com',
            'sec-fetch-site' => 'same-origin',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-dest' => 'empty',
            'Referer' => 'https://www.nykaaman.com?ptype=auth&root=myAccount_topBar',
            'Cookie' => 'storeId=men',
        ];
    }

    protected function requestQuery(string $target): array
    {
        return [
            'catalog_tag_filter' => 'men',
        ];
    }

    protected function requestBodyMode(): string
    {
        return 'form';
    }

    protected function requestBody(string $target): array
    {
        return [
            'email' => $target,
            'platform' => 'web',
            'captcha_type' => 'v3',
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
        if ($response->status() !== 200) {
            return ['Error', 'HTTP Error: ' . $response->status()];
        }

        $inner = (array) data_get($response->json(), 'response', []);
        $exists = $inner['is_exists'] ?? null;
        $message = strtolower((string) ($inner['message'] ?? ''));

        if ($exists === true || str_contains($message, 'already registered')) {
            return ['Registered', ''];
        }
        if ($exists === false || str_contains($message, 'welcome to nykaa')) {
            return ['Not Registered', ''];
        }

        return ['Error', 'Unexpected JSON response structure'];
    }
}
