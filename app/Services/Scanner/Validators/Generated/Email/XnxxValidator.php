<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class XnxxValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'xnxx';
    }

    public function category(): string
    {
        return 'adult';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Xnxx';
    }

    public function siteUrl(): string
    {
        return 'https://xnxx.com';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.xnxx.com/account/checkemail";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 5;
    }

    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36',
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'Accept-Encoding' => 'identity',
            'X-Requested-With' => 'XMLHttpRequest',
            'sec-ch-ua-platform' => '"Android"',
            'sec-ch-ua' => '"Google Chrome";v="143", "Chromium";v="143", "Not A(Brand";v="24"',
            'sec-ch-ua-mobile' => '?1',
            'Sec-Fetch-Site' => 'same-origin',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Dest' => 'empty',
            'Referer' => 'https://www.xnxx.com/',
            'Accept-Language' => 'en-US,en;q=0.9',
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
            return ['Error', 'Rate limited wait for few minutes'];
        }
        if ($status !== 200) {
            return ['Error', 'HTTP Error: ' . $status];
        }

        $data = $response->json();
        $result = $data['result'] ?? null;
        if ($result === true) {
            return ['Not Registered', ''];
        }
        if ($result === false) {
            return ['Registered', ''];
        }

        return ['Error', 'Unexpected error, report it via GitHub issues'];
    }
}
