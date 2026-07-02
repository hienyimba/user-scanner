<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ReplitValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'replit';
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
        return 'Replit';
    }

    public function siteUrl(): string
    {
        return 'https://replit.com';
    }

    protected function requestMethod(): string { return 'POST'; }

    protected function requestUrl(string $target): string
    {
        return "https://replit.com/data/user/exists";
    }

    protected function followRedirects(): bool
    {
        return false;
    }

    protected function timeoutSeconds(): int
    {
        return 5;
    }

    public function check(string $target, array $options = []): \App\DTO\ScanResult
    {
        try {
            $request = \Illuminate\Support\Facades\Http::timeout($this->timeoutSeconds())
                ->withOptions([
                    'allow_redirects' => false,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                    'version' => 1.1,
                ])
                ->withHeaders(array_merge([
                    'User-Agent' => config('scanner.user_agent'),
                    'Accept' => 'text/html,application/json,*/*;q=0.8',
                ], $this->requestHeaders()))
                ->withBody($this->requestRawBody($target) ?? '{}', 'application/json');

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->post($this->requestUrl($target));
            [$status, $reason] = $this->parseConnectorResponse($response, $target);

            return new \App\DTO\ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), $status, $reason, mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $reason = str_contains(strtolower($e->getMessage()), 'timed out')
                ? 'Request timeout'
                : $e->getMessage();

            return new \App\DTO\ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }

    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
            'Accept-Encoding' => 'identity',
            'Content-Type' => 'application/json',
            'sec-ch-ua-platform' => '"Windows"',
            'sec-ch-ua' => '"Not.A/Brand";v="8", "Chromium";v="144", "Google Chrome";v="144"',
            'sec-ch-ua-mobile' => '?0',
            'x-requested-with' => 'XMLHttpRequest',
            'origin' => 'https://replit.com',
            'sec-fetch-site' => 'same-origin',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-dest' => 'empty',
            'referer' => 'https://replit.com/signup',
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
        return 'raw';
    }

    protected function requestRawBody(string $target): ?string
    {
        return json_encode(['email' => $target], JSON_THROW_ON_ERROR);
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() === 403) return ['Error', '403 Forbidden'];
        $data = $response->json();
        $exists = $data['exists'] ?? null;
        if ($exists === true) return ['Registered', ''];
        if ($exists === false) return ['Not Registered', ''];
        return ['Error', 'Unexpected response format'];
    }
}
