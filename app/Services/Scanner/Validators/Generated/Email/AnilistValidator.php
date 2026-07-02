<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class AnilistValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'anilist';
    }

    public function category(): string
    {
        return 'entertainment';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Anilist';
    }

    public function siteUrl(): string
    {
        return 'https://anilist.co';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(7)
                ->withOptions([
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'schema' => 'internal',
                    'origin' => 'https://anilist.co',
                    'referer' => 'https://anilist.co/forgot-password',
                    'accept-language' => 'en-US,en;q=0.9',
                    'Accept-Encoding' => 'identity',
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->withBody(json_encode([
                'query' => 'mutation($email:String){ResetPassword(email:$email)}',
                'variables' => ['email' => $target],
            ], JSON_THROW_ON_ERROR), 'application/json')->post('https://anilist.co/graphql');

            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited wait for few minutes', mode: $this->mode(), key: $this->key());
            }

            $errors = $response->json('errors') ?? [];
            if (!is_array($errors) || $errors === []) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response body structure (No errors returned)', mode: $this->mode(), key: $this->key());
            }

            $firstError = is_array($errors[0] ?? null) ? $errors[0] : [];
            $message = strtolower((string) ($firstError['message'] ?? ''));
            if (str_contains($message, 'unauthorized')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (str_contains($message, 'validation')) {
                $validation = is_array($firstError['validation'] ?? null) ? $firstError['validation'] : [];
                $emailErrors = is_array($validation['email'] ?? null) ? $validation['email'] : [];
                foreach ($emailErrors as $error) {
                    if (str_contains(strtolower((string) $error), 'invalid')) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
                    }
                }

                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected validation error message', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected error type: ' . $message . ', report it via GitHub issues', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = str_contains($message, 'timed out')
                ? (str_contains($message, 'read') ? 'Server took too long to respond (Read Timeout)' : 'Connection timed out! maybe region blocks')
                : $e->getMessage();

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
