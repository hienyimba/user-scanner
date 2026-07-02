<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class CnnValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'cnn';
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
        return 'Cnn';
    }

    public function siteUrl(): string
    {
        return 'https://cnn.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(5)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36',
                'Content-Type' => 'application/json',
                'x-client-application' => 'Android|Android 10|Chrome 144.0.0.0',
                'Origin' => 'https://edition.cnn.com',
                'Referer' => 'https://edition.cnn.com/',
                'Accept-Encoding' => 'identity',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->withBody(json_encode([
                'identityRequests' => [[
                    'identityType' => 'EMAIL',
                    'principal' => $target,
                    'credential' => 'th3_sil3nt_fir3wall_hid3s_most',
                ]],
            ], JSON_THROW_ON_ERROR), 'application/json')->post('https://audience.cnn.com/core/api/1/identity');

            $body = $response->body();
            if (str_contains($body, 'identity.already.in.use')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (str_contains($body, 'cnn.createprofile') && str_contains($body, 'cnn.updatepassword')) {
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
