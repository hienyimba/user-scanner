<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class AdobeValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'adobe';
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
        return 'Adobe';
    }

    public function siteUrl(): string
    {
        return 'https://adobe.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return "https://auth.services.adobe.com/signin/v2/users/accounts";
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
            'Accept' => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
            'x-ims-clientid' => 'BehanceWebSusi1',
            'Origin' => 'https://auth.services.adobe.com',
            'Referer' => 'https://www.behance.net/',
        ];
    }

    protected function requestRawBody(string $target): ?string
    {
        return json_encode([
            'username' => $target,
            'usernameType' => 'EMAIL',
        ], JSON_UNESCAPED_SLASHES);
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $data = $response->json();
        if (!is_array($data) || array_is_list($data) === false) {
            return ['Error', 'Unexpected response body, report it on github'];
        }
        if ($data === []) {
            return ['Not Registered', ''];
        }

        foreach ($data as $account) {
            $methods = is_array($account) ? ($account['authenticationMethods'] ?? []) : [];
            if (is_array($methods)) {
                foreach ($methods as $method) {
                    if (($method['id'] ?? null) === 'password') {
                        return ['Registered', ''];
                    }
                }
            }
        }

        return ['Not Registered', ''];
    }
}
