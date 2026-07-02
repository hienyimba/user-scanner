<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;

final class AlisonValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'alison';
    }

    public function category(): string
    {
        return 'learning';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Alison';
    }

    public function siteUrl(): string
    {
        return 'https://alison.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $cookieJar = new CookieJar();

        try {
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Origin' => 'https://alison.com',
                'Referer' => 'https://alison.com/',
                'Accept-Encoding' => 'identity',
            ];

            $request = Http::timeout(7)->withOptions([
                'allow_redirects' => true,
                'cookies' => $cookieJar,
                'verify' => (bool) config('scanner.verify_ssl', false),
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $init = $request->withHeaders($headers)->get('https://alison.com/login');
            if (!preg_match('/name="_token"\s+value="([^"]+)"/', $init->body(), $match)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unable to extract CSRF token from Alison', mode: $this->mode(), key: $this->key());
            }

            $response = $request->withHeaders($headers)->asForm()->post('https://alison.com/register', [
                '_token' => $match[1],
                'firstname' => 'The',
                'lastname' => 'SilentSowrd',
                'signup_email' => $target,
                'signup_password' => '',
                'signup_tc_social' => '1',
                'current' => 'https://alison.com',
                'route_name' => 'site.home',
            ]);

            $body = $response->body();
            $lowerBody = strtolower($body);
            if (
                str_contains($body, 'The signup email has already been taken')
                || str_contains($lowerBody, 'signup email has already been taken')
                || str_contains($lowerBody, 'email has already been taken')
                || str_contains($lowerBody, 'already exists')
            ) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (
                (str_contains($body, 'id="emailNew"')
                || str_contains($body, 'name="signup_email"')
                || str_contains($lowerBody, 'signup_email')
                || str_contains($lowerBody, 'id="emailnew"'))
                && !str_contains($lowerBody, 'already been taken')
            ) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            if (
                str_contains($lowerBody, 'window.nreum')
                || str_contains($lowerBody, '__next')
                || str_contains($lowerBody, 'newrelic')
            ) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Alison signup now appears to be JS-shell / reCAPTCHA gated, so the old non-interactive email check flow is no longer parsable', mode: $this->mode(), key: $this->key());
            }

            $snippet = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');
            $snippet = mb_substr($snippet, 0, 160);
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response body, report it on github' . ($snippet !== '' ? ' | ' . $snippet : ''), mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = str_contains($message, 'timed out')
                ? (str_contains($message, 'read') ? 'Server took too long to respond (Alison)' : 'Connection timed out (Alison)')
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
