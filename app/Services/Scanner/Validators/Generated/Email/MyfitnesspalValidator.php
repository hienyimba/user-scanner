<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class MyfitnesspalValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'myfitnesspal';
    }

    public function category(): string
    {
        return 'fitness';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Myfitnesspal';
    }

    public function siteUrl(): string
    {
        return 'https://www.myfitnesspal.com';
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
                    'sec-ch-ua-platform' => '"Android"',
                    'sec-ch-ua' => '"Not:A-Brand";v="99", "Google Chrome";v="145", "Chromium";v="145"',
                    'Referer' => 'https://www.myfitnesspal.com/account/create',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Priority' => 'u=1, i',
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->get('https://www.myfitnesspal.com/api/idm/user-exists', [
                'email' => $target,
            ]);

            if ($response->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF or IP Block (403)', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited by MyFitnessPal (429)', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'HTTP Error: ' . $response->status(), mode: $this->mode(), key: $this->key());
            }

            $emailExists = $response->json('emailExists');
            if ($emailExists === true) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if ($emailExists === false) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response body structure', mode: $this->mode(), key: $this->key());
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
