<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class ZohoValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'zoho';
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
        return 'Zoho';
    }

    public function siteUrl(): string
    {
        return 'https://zoho.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $showUrl = $this->siteUrl();
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
            'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
            'Accept' => '*/*',
            'Origin' => 'https://accounts.zoho.com',
            'Referer' => 'https://accounts.zoho.com/',
            'Accept-Language' => 'en-US,en;q=0.9',
        ];

        try {
            $jar = new CookieJar();
            $client = Http::timeout(5)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                    'cookies' => $jar,
                ])
                ->withHeaders($headers);

            if (!empty($options['proxy'])) {
                $client = $client->withOptions(['proxy' => $options['proxy']]);
            }

            $client->get('https://accounts.zoho.com/signin');
            $csrfCookie = $jar->getCookieByName('iamcsr');
            if ($csrfCookie === null) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'CSRF cookie not found', mode: $this->mode(), key: $this->key());
            }

            $response = $client
                ->withHeaders(array_merge($headers, [
                    'X-ZCSRF-TOKEN' => 'iamcsrcoo=' . $csrfCookie->getValue(),
                ]))
                ->asForm()
                ->post('https://accounts.zoho.com/signin/v2/lookup/' . $target, [
                    'mode' => 'primary',
                    'servicename' => 'ZohoCRM',
                    'serviceurl' => 'https://crm.zoho.com/crm/ShowHomePage.do',
                    'service_language' => 'en',
                ]);

            if (in_array($response->status(), [200, 400], true)) {
                $body = $response->body();
                $data = $response->json();
                $status = $data['status_code'] ?? null;
                $message = (string) ($data['message'] ?? '');
                $bodyLower = strtolower($body);
                $messageLower = strtolower($message);

                if ($status === 201 || $message === 'User exists') {
                    return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Registered', '', mode: $this->mode(), key: $this->key());
                }
                if ($status === 400) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Not Registered', '', mode: $this->mode(), key: $this->key());
                }
                if (str_contains($message, 'User exists in another DC')) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Registered', '', mode: $this->mode(), key: $this->key());
                }
                if (
                    str_contains($messageLower, 'user exists')
                    || str_contains($messageLower, 'another dc')
                    || str_contains($bodyLower, 'user exists')
                    || str_contains($bodyLower, 'another dc')
                    || str_contains($bodyLower, '"status_code":201')
                ) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Registered', '', mode: $this->mode(), key: $this->key());
                }
                if (
                    str_contains($messageLower, 'no user')
                    || str_contains($bodyLower, '"status_code":400')
                    || str_contains($bodyLower, '"statuscode":400')
                ) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
                }

                $snippet = substr(preg_replace('/\s+/', ' ', $body) ?? '', 0, 180);
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Unexpected response body, report it via GitHub issues' . ($snippet !== '' ? ' | ' . $snippet : ''), mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'HTTP ' . $response->status(), mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Unexpected Exception: ' . $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Zoho uses a custom CSRF lookup flow'];
    }
}
