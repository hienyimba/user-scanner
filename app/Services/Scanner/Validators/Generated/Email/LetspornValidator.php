<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class LetspornValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'letsporn';
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
        return 'Letsporn';
    }

    public function siteUrl(): string
    {
        return 'https://letsporn.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return "https://letsporn.com";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 15;
    }

    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'identity',
            'sec-ch-ua-platform' => '"Android"',
            'X-Requested-With' => 'XMLHttpRequest',
            'Origin' => 'https://letsporn.com',
            'Referer' => 'https://letsporn.com/categories/lesbian',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestQuery(string $target): array
    {
        return [
            'mode' => 'async',
            'function' => 'get_block',
            'block_id' => 'signup_signup_form_simple',
            'global' => 'true',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestBody(string $target): array
    {
        return [
            'format' => 'json',
            'mode' => 'async',
            'action' => 'signup',
            'email_link' => 'https://letsporn.com/email',
            'email' => $target,
            'username' => $target,
            'pass' => 'y0u_hav3_th3_s0wrd',
            'pass2' => 'but_n0t_th3_cr0wn',
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
        $errors = $data['errors'] ?? [];
        foreach ($errors as $error) {
            if (is_array($error)) {
                $code = $error['code'] ?? null;
                $field = $error['field'] ?? null;
                if ($code === 'exists' && in_array($field, ['username', 'email'], true)) {
                    return ['Registered', ''];
                }
            } elseif (is_string($error)) {
                $message = strtolower($error);
                if (str_contains($message, 'already exists') || str_contains($message, 'already in use')) {
                    return ['Registered', ''];
                }
            }
        }

        return ['Not Registered', ''];
    }
}
