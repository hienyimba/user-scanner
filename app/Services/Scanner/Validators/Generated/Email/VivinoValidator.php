<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Support\Facades\Http;

final class VivinoValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'vivino';
    }

    public function category(): string
    {
        return 'shopping';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Vivino';
    }

    public function siteUrl(): string
    {
        return 'https://vivino.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(10)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                'Accept' => 'application/json',
                'Referer' => 'https://www.vivino.com/',
                'Accept-Language' => 'en-US,en;q=0.9',
                'X-Requested-With' => 'XMLHttpRequest',
                'Content-Type' => 'application/json',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $request->get('https://www.vivino.com/');

            $response = $request->post('https://www.vivino.com/api/login', [
                'email' => $target,
                'password' => 'password123',
            ]);

            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limit exceeded', mode: $this->mode(), key: $this->key());
            }

            $error = (string) ($response->json('error') ?? '');
            if ($error === 'The supplied email does not exist') {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }
            if ($error === '' || str_contains(strtolower($error), 'password')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (str_contains(strtolower($error), 'account has been locked')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', 'Account is locked by Vivino', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Vivino Error: ' . $error, mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $reason = str_contains(strtolower($e->getMessage()), 'timed out')
                ? 'Connection timed out'
                : $e->getMessage();

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }
}
