<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class PinterestValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'pinterest';
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
        return 'Pinterest';
    }

    public function siteUrl(): string
    {
        return 'https://pinterest.com';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.pinterest.com/resource/ApiResource/get/";
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
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36',
            'Accept' => 'application/json, text/javascript, */*, q=0.01',
            'Accept-Language' => 'en-US,en;q=0.9',
            'x-pinterest-pws-handler' => 'www/signup/[step].js',
            'x-app-version' => '2503cde',
            'x-requested-with' => 'XMLHttpRequest',
            'x-pinterest-source-url' => '/signup/step1/',
            'x-pinterest-appstate' => 'active',
            'origin' => 'https://www.pinterest.com',
            'referer' => 'https://www.pinterest.com/',
            'sec-fetch-site' => 'same-origin',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-dest' => 'empty',
            'priority' => 'u=1, i',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestQuery(string $target): array
    {
        $payload = [
            'options' => [
                'url' => '/v3/register/exists/',
                'data' => ['email' => $target],
            ],
            'context' => new \stdClass(),
        ];

        return [
            'source_url' => '/signup/step1/',
            'data' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            '_' => (string) round(microtime(true) * 1000),
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 200) {
            $json = $response->json();
            $exists = data_get($json, 'resource_response.data');

            if ($exists === true) {
                return ['Registered', ''];
            }
            if ($exists === false) {
                return ['Not Registered', ''];
            }

            return ['Error', 'Unexpected response body, report it via GitHub issues'];
        }

        if ($status === 403) {
            return ['Error', 'Access Forbidden (403) - Potential IP Block'];
        }
        if ($status === 429) {
            return ['Error', 'Rate limited (429)'];
        }

        return ['Error', 'HTTP ' . $status];
    }
}
