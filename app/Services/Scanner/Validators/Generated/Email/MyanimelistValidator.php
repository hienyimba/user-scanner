<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class MyanimelistValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'myanimelist';
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
        return 'Myanimelist';
    }

    public function siteUrl(): string
    {
        return 'https://myanimelist.net';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(7)
                ->withOptions([
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
                    'Accept' => 'application/json, text/plain, */*',
                    'Accept-Encoding' => 'identity',
                    'sec-ch-ua-platform' => '"Android"',
                    'x-requested-with' => 'XMLHttpRequest',
                    'origin' => 'https://myanimelist.net',
                    'sec-fetch-site' => 'same-origin',
                    'sec-fetch-mode' => 'cors',
                    'sec-fetch-dest' => 'empty',
                    'referer' => 'https://myanimelist.net/register.php?',
                    'accept-language' => 'en-US,en;q=0.9',
                    'priority' => 'u=1, i',
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->asForm()->post('https://myanimelist.net/signup/email/validate', [
                'email' => $target,
            ]);

            if ($response->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF or IP Block (403)', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited wait for few minutes', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'HTTP Error: ' . $response->status(), mode: $this->mode(), key: $this->key());
            }

            $data = $response->json();
            $errors = is_array($data['errors'] ?? null) ? $data['errors'] : [];
            foreach ($errors as $error) {
                $message = strtolower((string) ($error['message'] ?? ''));
                if (str_contains($message, 'already have an account')) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
                }
            }

            $respData = $data['data'] ?? null;
            if (is_array($respData) && $respData === []) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response body structure, report it via GitHub issues', mode: $this->mode(), key: $this->key());
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
