<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class PatreonValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'patreon';
    }

    public function category(): string
    {
        return 'creator';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Patreon';
    }

    public function siteUrl(): string
    {
        return 'https://patreon.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.patreon.com/api/auth";
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
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36',
            'Accept-Encoding' => 'identity',
            'Accept' => 'application/vnd.api+json, application/json, text/plain, */*',
            'content-type' => 'application/vnd.api+json',
            'origin' => 'https://www.patreon.com',
            'referer' => 'https://www.patreon.com/',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestQuery(string $target): array
    {
        return [
            'include' => 'user.null',
            'fields[user]' => '[]',
            'json-api-version' => '1.0',
            'json-api-use-default-includes' => 'false',
        ];
    }

    protected function requestRawBody(string $target): ?string
    {
        return json_encode([
            'data' => [
                'type' => 'genericPatreonApi',
                'attributes' => [
                    'patreon_auth' => [
                        'email' => $target,
                        'allow_account_creation' => false,
                    ],
                    'auth_context' => 'auth',
                    'ru' => 'https://www.patreon.com/home',
                ],
                'relationships' => new \stdClass(),
            ],
        ], JSON_UNESCAPED_SLASHES);
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status !== 200) {
            return ['Error', 'Status ' . $status];
        }

        $data = $response->json();
        $nextStep = data_get($data, 'data.attributes.next_auth_step');
        if ($nextStep === 'password') {
            return ['Registered', ''];
        }
        if ($nextStep === 'signup') {
            return ['Not Registered', ''];
        }

        return ['Error', 'Unexpected auth step'];
    }
}
