<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class JustwatchValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'justwatch';
    }

    public function category(): string
    {
        return 'entertainment';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Justwatch';
    }

    public function siteUrl(): string
    {
        return 'https://justwatch.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(5)
                ->withOptions([
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36',
                    'Content-Type' => 'application/json',
                    'x-client-version' => 'Chrome/JsCore/10.14.1/FirebaseCore-web',
                    'origin' => 'https://www.justwatch.com',
                    'referer' => 'https://www.justwatch.com/',
                    'Accept-Encoding' => 'identity',
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->post('https://identitytoolkit.googleapis.com/v1/accounts:createAuthUri?key=AIzaSyDv6JIzdDvbTBS-JWdR4Kl22UvgWGAyuo8', [
                'identifier' => $target,
                'continueUri' => 'https://www.justwatch.com/',
            ]);

            if ($response->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'HTTP ' . $response->status(), mode: $this->mode(), key: $this->key());
            }

            $registered = $response->json('registered');
            if ($registered === true) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if ($registered === false) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response body, report it via GitHub issues', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $reason = str_contains(strtolower($e->getMessage()), 'timed out') ? 'Connection timed out' : 'Unexpected Exception: ' . $e->getMessage();
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
