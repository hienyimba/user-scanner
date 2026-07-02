<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class AddictinggamesValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'addictinggames';
    }

    public function category(): string
    {
        return 'gaming';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Addictinggames';
    }

    public function siteUrl(): string
    {
        return 'https://addictinggames.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(6)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Content-Type' => 'application/json',
                'Origin' => 'https://www.addictinggames.com',
                'Referer' => 'https://www.addictinggames.com/',
                'Accept-Encoding' => 'identity',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request
                ->withBody(json_encode([
                    'name' => [['value' => 'tierd_knight']],
                    'mail' => [['value' => $target]],
                    'pass' => [['value' => 'n0_0ne_asked_just_fight']],
                    'field_opt_in' => [['value' => false]],
                ], JSON_THROW_ON_ERROR), 'application/json')
                ->post('https://prod.addictinggames.com/user/registerpass?_format=json');

            $body = $response->body();
            if ($response->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Cloudflare Bot Detection (403 Forbidden), try after sometime or use proxy', mode: $this->mode(), key: $this->key());
            }
            if (str_contains($body, 'mail: The email address') && str_contains($body, 'is already taken')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (str_contains($body, 'name: The username tierd_knight is already taken')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response body, report it on github', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
