<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class FapfolderValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'fapfolder';
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
        return 'Fapfolder';
    }

    public function siteUrl(): string
    {
        return 'https://fapfolder.club';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return "https://fapfolder.club/includes/ajax/core/signup.php";
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
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36',
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'Accept-Encoding' => 'identity',
            'sec-ch-ua-platform' => '"Android"',
            'x-requested-with' => 'XMLHttpRequest',
            'sec-ch-ua' => '"Chromium";v="146", "Not-A.Brand";v="24", "Google Chrome";v="146"',
            'sec-ch-ua-mobile' => '?1',
            'origin' => 'https://fapfolder.club',
            'sec-fetch-site' => 'same-origin',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-dest' => 'empty',
            'referer' => 'https://fapfolder.club/signup',
            'accept-language' => 'en-US,en;q=0.9',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestBody(string $target): array
    {
        return [
            'username' => 'l0v3_d0n0t_3xist',
            'email' => $target,
            'email2' => $target,
            'password' => '1',
            'field1' => '',
            'privacy_agree' => 'on',
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
        $message = (string) ($data['message'] ?? '');
        if (str_contains($message, 'belongs to an existing account')) {
            return ['Registered', ''];
        }
        if (str_contains($message, 'password must be at least')) {
            return ['Not Registered', ''];
        }

        return ['Error', 'Unexpected: ' . substr($message, 0, 50)];
    }
}
