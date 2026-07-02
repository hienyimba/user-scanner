<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ThegayValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'thegay';
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
        return 'Thegay';
    }

    public function siteUrl(): string
    {
        return 'https://thegay.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return "https://thegay.com/api/signup.php";
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
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'identity',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'sec-ch-ua-platform' => '"Android"',
            'Origin' => 'https://thegay.com',
            'Referer' => 'https://thegay.com/signup/',
            'Priority' => 'u=1, i',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestBody(string $target): array
    {
        return [
            'act' => 'signup',
            'usr' => 'th3_knight_l0st',
            'eml' => $target,
            'pwd' => 'youAr3Al0n3',
        ];
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

        $data = $response->json();
        $errors = $data['errors'] ?? [];
        $isTaken = false;
        $hasTokenRequired = false;
        foreach ($errors as $error) {
            if (!is_array($error)) {
                continue;
            }
            if (($error['code'] ?? null) === 'eml_occupied') {
                $isTaken = true;
            }
            if (($error['code'] ?? null) === 'tok_required') {
                $hasTokenRequired = true;
            }
        }

        if ($isTaken) {
            return ['Registered', ''];
        }
        if ($hasTokenRequired) {
            return ['Not Registered', ''];
        }

        return ['Error', 'Unexpected response body structure'];
    }
}
