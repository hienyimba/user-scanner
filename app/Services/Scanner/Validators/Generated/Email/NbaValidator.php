<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Support\Facades\Http;

final class NbaValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'nba';
    }

    public function category(): string
    {
        return 'sports';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Nba';
    }

    public function siteUrl(): string
    {
        return 'https://www.nba.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(10)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
                'Accept-Encoding' => 'identity',
                'Content-Type' => 'application/json',
                'sec-ch-ua-platform' => '"Android"',
                'x-client-platform' => 'web',
                'origin' => 'https://www.nba.com',
                'referer' => 'https://www.nba.com/',
                'accept-language' => 'en-US,en;q=0.9',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->withBody(json_encode(['email' => $target], JSON_THROW_ON_ERROR), 'application/json')
                ->post('https://identity.nba.com/api/v1/profile/registrationStatus');

            if ($response->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF or IP Block (403)', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited by NBA', mode: $this->mode(), key: $this->key());
            }

            if ($response->status() === 200) {
                if ($response->json('status') === 'success' && $response->json('data.isFull') === true) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
                }
            }

            if ($response->status() === 400) {
                $errorCodes = $response->json('errorCodes') ?? [];
                $message = (string) ($response->json('data.message') ?? '');

                if (in_array('INVALID_PROFILE_STATUS', $errorCodes, true) || str_contains($message, 'Profile not found')) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
                }
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected status code: ' . $response->status(), mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $reason = str_contains(strtolower($e->getMessage()), 'timed out')
                ? 'Connection timed out! maybe region blocks'
                : $e->getMessage();

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }
}
