<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class StremioValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'stremio';
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
        return 'Stremio';
    }

    public function siteUrl(): string
    {
        return 'https://stremio.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(5)
                ->withOptions([
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36',
                    'Content-Type' => 'application/json',
                    'Origin' => 'https://www.stremio.com',
                    'Referer' => 'https://www.stremio.com/',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Accept-Encoding' => 'identity',
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->post('https://api.strem.io/api/login', [
                'authKey' => null,
                'email' => $target,
                'password' => 'wrongpassword123',
            ]);

            if ($response->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'HTTP ' . $response->status(), mode: $this->mode(), key: $this->key());
            }

            $errorData = is_array($response->json('error')) ? $response->json('error') : [];
            if (($errorData['wrongPass'] ?? false) === true) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (($errorData['wrongEmail'] ?? false) === true) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response body, report it via GitHub issues', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $reason = str_contains(strtolower($e->getMessage()), 'timed out') ? 'Connection timed out' : 'Unexpected Exception: ' . $e->getMessage();
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
