<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class NebulaTvValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'nebula_tv';
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
        return 'NebulaTv';
    }

    public function siteUrl(): string
    {
        return 'https://nebula.tv';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(10)
                ->withOptions([
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
                    'Accept' => 'application/json, text/plain, */*',
                    'Accept-Encoding' => 'identity',
                    'Content-Type' => 'application/json',
                    'nebula-app-version' => '26.3.0',
                    'nebula-platform' => 'web',
                    'Origin' => 'https://nebula.tv',
                    'Referer' => 'https://nebula.tv/join',
                    'Priority' => 'u=1, i',
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->withBody(json_encode([
                'email' => $target,
                'password' => '5',
                'agreed_to_terms' => true,
                'opt_in_to_communications' => false,
            ], JSON_THROW_ON_ERROR), 'application/json')->post('https://nebula.tv/auth/registration/');

            if ($response->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF or IP Block (403)', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited by Nebula (429)', mode: $this->mode(), key: $this->key());
            }

            $data = $response->json();
            if (array_key_exists('email', $data)) {
                $emailErrors = is_array($data['email']) ? $data['email'] : [];
                foreach ($emailErrors as $error) {
                    if (str_contains(strtolower((string) $error), 'already registered')) {
                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
                    }
                }
            }
            if (array_key_exists('password', $data) && !array_key_exists('email', $data)) {
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
