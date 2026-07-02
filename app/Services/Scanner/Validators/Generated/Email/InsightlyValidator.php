<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class InsightlyValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'insightly';
    }

    public function category(): string
    {
        return 'crm';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Insightly';
    }

    public function siteUrl(): string
    {
        return 'https://insightly.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $showUrl = $this->siteUrl();
        $headers = [
            'accept' => 'application/json, text/javascript, */*; q=0.01',
            'Accept-Encoding' => 'identity',
            'x-requested-with' => 'XMLHttpRequest',
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
            'content-type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'origin' => 'https://accounts.insightly.com',
            'referer' => 'https://accounts.insightly.com/?plan=trial',
            'accept-language' => 'en-US,en;q=0.9',
        ];
        try {
            $client = Http::timeout(5)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ]);

            if (!empty($options['proxy'])) {
                $client = $client->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $client
                ->withHeaders($headers)
                ->withBody(http_build_query(['emailaddress' => $target]), 'application/x-www-form-urlencoded; charset=UTF-8')
                ->post('https://accounts.insightly.com/signup/isemailvalid');

            $body = trim($response->body());
            $bodyLower = strtolower($body);
            if (str_contains($body, 'An account exists for this address.')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if ($body === 'true' || $bodyLower === '"true"') {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }
            if ($body === 'false' || $bodyLower === '"false"') {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (str_contains($bodyLower, 'account exists')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (str_contains($bodyLower, 'email valid') || str_contains($bodyLower, 'is valid')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            $snippet = substr(preg_replace('/\s+/', ' ', $body) ?? '', 0, 160);
            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Unexpected response: ' . $response->status() . ($snippet !== '' ? ' | ' . $snippet : ''), mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Insightly uses a custom session flow'];
    }
}
