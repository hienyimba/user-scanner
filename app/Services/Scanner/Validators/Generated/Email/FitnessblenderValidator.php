<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;

final class FitnessblenderValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'fitnessblender';
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
        return 'Fitnessblender';
    }

    public function siteUrl(): string
    {
        return 'https://www.fitnessblender.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $cookieJar = new CookieJar();

        try {
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Encoding' => 'identity',
                'sec-ch-ua-platform' => '"Android"',
                'Origin' => 'https://www.fitnessblender.com',
                'Referer' => 'https://www.fitnessblender.com/join',
                'X-Requested-With' => 'XMLHttpRequest',
                'Priority' => 'u=1, i',
            ];

            $request = Http::timeout(20)->withOptions([
                'allow_redirects' => true,
                'cookies' => $cookieJar,
                'verify' => (bool) config('scanner.verify_ssl', false),
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $init = $request->withHeaders($headers)->get('https://www.fitnessblender.com');
            if ($init->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Handshake failed: ' . $init->status(), mode: $this->mode(), key: $this->key());
            }

            $csrfToken = null;
            foreach ($cookieJar->toArray() as $cookie) {
                if (($cookie['Name'] ?? null) === 'XSRF-TOKEN') {
                    $csrfToken = (string) ($cookie['Value'] ?? '');
                    break;
                }
            }

            if (($csrfToken === null || $csrfToken === '') && preg_match('/csrfToken:\s*"([^"]+)"/', $init->body(), $match)) {
                $csrfToken = $match[1];
            }

            if ($csrfToken === null || $csrfToken === '') {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'CSRF token not found', mode: $this->mode(), key: $this->key());
            }

            $headers['x-csrf-token'] = $csrfToken;
            $headers['Content-Type'] = 'application/json';

            $response = $request->withHeaders($headers)
                ->withBody(json_encode(['email' => $target, 'force' => 0], JSON_THROW_ON_ERROR), 'application/json')
                ->post('https://www.fitnessblender.com/api/v1/validate/unique-email');

            if ($response->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF (403)', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() === 419) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'CSRF Mismatch/Expired (419)', mode: $this->mode(), key: $this->key());
            }

            $status = strtolower((string) $response->json('status'));
            $message = strtolower((string) $response->json('message'));

            if ($status === 'error' && str_contains($message, 'already registered')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if ($status === 'success' && $message === 'ok') {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response: ' . $message, mode: $this->mode(), key: $this->key());
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
