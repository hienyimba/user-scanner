<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class GlobaltimesValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'globaltimes';
    }

    public function category(): string
    {
        return 'news';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Globaltimes';
    }

    public function siteUrl(): string
    {
        return 'https://globaltimes.cn';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(10)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36',
                'Content-Type' => 'application/json;charset=UTF-8',
                'Token' => 'null',
                'Vcode' => '232',
                'Origin' => 'https://enapp.globaltimes.cn',
                'Referer' => 'https://enapp.globaltimes.cn/web/login',
                'Accept-Encoding' => 'identity',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->withBody(json_encode([
                'mail' => $target,
                'password' => '21A5F558F45BE7FEA45A47EF4CAEC71B',
                'sensorsDistinctId' => '',
                'sensorsAnonymousId' => '',
            ], JSON_THROW_ON_ERROR), 'application/json;charset=UTF-8')->post('https://enapp.globaltimes.cn/api/user/login');

            $message = (string) $response->json('msg');
            foreach ([
                'password is incorrect',
                'account will be locked',
                'number of retries has reached the maximum',
            ] as $indicator) {
                if (str_contains($message, $indicator)) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
                }
            }
            if (str_contains($message, "don't recognize this account")) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response body, report it on github', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = str_contains($message, 'timed out')
                ? (str_contains($message, 'read') ? 'Server took too long to respond (Read Timeout)' : 'Connection timed out!')
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
