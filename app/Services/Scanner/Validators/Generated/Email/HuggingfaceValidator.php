<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class HuggingfaceValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'huggingface';
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
        return 'Huggingface';
    }

    public function siteUrl(): string
    {
        return 'https://huggingface.co';
    }

    protected function requestMethod(): string { return 'POST'; }

    protected function requestUrl(string $target): string
    {
        return "https://huggingface.co/api/check-user-email";
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
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',
            'Accept-Encoding' => 'identity',
            'referer' => 'https://huggingface.co/join',
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
        if ($response->status() === 429) return ['Error', 'Rate limited wait for few minutes'];
        if ($response->status() === 200) {
            $text = $response->body();
            if (str_contains($text, 'already exists')) return ['Registered', ''];
            if (str_contains($text, 'This email address is available.')) return ['Not Registered', ''];
        }
        return ['Error', 'HTTP Error: ' . $response->status() . ', report it via GitHub issues'];
    }
}
