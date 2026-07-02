<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;

final class LetterboxdValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'letterboxd';
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
        return 'Letterboxd';
    }

    public function siteUrl(): string
    {
        return 'https://letterboxd.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $cookieJar = new CookieJar();

        try {
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36',
                'Accept' => 'application/json, text/javascript, */*; q=0.01',
                'X-Requested-With' => 'XMLHttpRequest',
                'Origin' => 'https://letterboxd.com',
                'Referer' => 'https://letterboxd.com/register/standalone/',
                'Accept-Encoding' => 'identity',
            ];

            $request = Http::timeout(10)->withOptions([
                'allow_redirects' => true,
                'cookies' => $cookieJar,
                'verify' => (bool) config('scanner.verify_ssl', false),
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $request->withHeaders(['User-Agent' => $headers['User-Agent']])->get('https://letterboxd.com/sign-in/');

            $csrfToken = null;
            foreach ($cookieJar->toArray() as $cookie) {
                if (($cookie['Name'] ?? null) === 'com.xk72.webparts.csrf') {
                    $csrfToken = (string) ($cookie['Value'] ?? '');
                    break;
                }
            }

            if ($csrfToken === null || $csrfToken === '') {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Could not extract Letterboxd CSRF token', mode: $this->mode(), key: $this->key());
            }

            $response = $request->withHeaders($headers)->asForm()->post('https://letterboxd.com/user/standalone/register.do', [
                '__csrf' => $csrfToken,
                'token' => '',
                'emailAddress' => $target,
                'username' => 'th3_t3erminal_w0rri0r',
                'password' => 'n3v3r_F3lt_softn3ss',
                'termsAndAge' => 'true',
                'g-recaptcha-response' => '',
                'h-captcha-response' => '',
            ]);

            $data = $response->json();
            if (!is_array($data)) {
                $body = strtolower($response->body());

                if (str_contains($body, 'already associated with an account')) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
                }

                if (
                    str_contains($body, 'join letterboxd')
                    || str_contains($body, 'create account')
                    || str_contains($body, 'email address')
                ) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
                }

                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response structure', mode: $this->mode(), key: $this->key());
            }

            $messages = is_array($data['messages'] ?? null) ? $data['messages'] : [];
            $errorFields = is_array($data['errorFields'] ?? null) ? $data['errorFields'] : [];
            $isTaken = false;
            foreach ($messages as $message) {
                if (str_contains(strtolower((string) $message), 'already associated with an account')) {
                    $isTaken = true;
                    break;
                }
            }

            if ($isTaken || in_array('emailAddress', $errorFields, true)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (isset($data['result']) && !$isTaken) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response structure', mode: $this->mode(), key: $this->key());
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
