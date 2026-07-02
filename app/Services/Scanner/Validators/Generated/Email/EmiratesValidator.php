<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Support\Facades\Http;

final class EmiratesValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'emirates';
    }

    public function category(): string
    {
        return 'travel';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Emirates';
    }

    public function siteUrl(): string
    {
        return 'https://emirates.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(10)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Encoding' => 'identity',
                'Content-Type' => 'application/json',
                'sec-ch-ua-platform' => '"Android"',
                'sec-ch-ua' => '"Not:A-Brand";v="99", "Google Chrome";v="145", "Chromium";v="145"',
                'sec-ch-ua-mobile' => '?1',
                'origin' => 'https://www.emirates.com',
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-dest' => 'empty',
                'accept-language' => 'en-US,en;q=0.9',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->withBody(json_encode(['emailAddress' => $target], JSON_THROW_ON_ERROR), 'application/json')
                ->post('https://www.emirates.com/service/ekl/validate-email?data=null');

            if ($response->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF or IP Block (403)', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited by Emirates', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected status code: ' . $response->status(), mode: $this->mode(), key: $this->key());
            }

            $errors = $response->json('body.errors') ?? [];
            foreach ($errors as $error) {
                if (($error['code'] ?? null) === 'program.member.EmailExistsForAnotherMember') {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
                }
            }

            if ($response->json('status') === true) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $reason = str_contains(strtolower($e->getMessage()), 'timed out')
                ? 'Connection timed out! maybe region blocks'
                : $e->getMessage();

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }
}
