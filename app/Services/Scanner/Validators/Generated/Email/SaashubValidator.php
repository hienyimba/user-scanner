<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;

final class SaashubValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'saashub';
    }

    public function category(): string
    {
        return 'dev';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Saashub';
    }

    public function siteUrl(): string
    {
        return 'https://saashub.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $cookieJar = new CookieJar();

        try {
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'accept-language' => 'en-US,en;q=0.9',
                'referer' => 'https://www.google.com/',
                'accept-encoding' => 'identity',
            ];

            $request = Http::timeout(10)->withOptions([
                'allow_redirects' => true,
                'cookies' => $cookieJar,
                'verify' => (bool) config('scanner.verify_ssl', false),
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $get = $request->withHeaders($headers)->get('https://www.saashub.com/register');
            if ($get->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Failed to load register page: ' . $get->status(), mode: $this->mode(), key: $this->key());
            }
            if (!preg_match('/name="authenticity_token" value="([^"]+)"/', $get->body(), $match)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Could not find authenticity_token in response', mode: $this->mode(), key: $this->key());
            }

            $postHeaders = $headers + [
                'Accept' => 'text/vnd.turbo-stream.html, text/html, application/xhtml+xml',
                'origin' => 'https://www.saashub.com',
                'referer' => 'https://www.saashub.com/register',
                'x-turbo-request-id' => (string) \Illuminate\Support\Str::uuid(),
            ];

            $response = $request->withHeaders($postHeaders)->asForm()->post('https://www.saashub.com/register', [
                'authenticity_token' => $match[1],
                'user[email]' => $target,
                'user[username]' => 't3rminalw0rri0r' . substr((string) time(), -4),
                'user[password]' => '',
                'user[password_confirmation]' => '',
                'company_name' => '',
                'commit' => 'Register',
            ]);

            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited wait for few minutes', mode: $this->mode(), key: $this->key());
            }
            $text = $response->body();
            if (str_contains($text, 'Email - has already been taken')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (str_contains($text, "We couldn't sign you up") && !str_contains($text, 'Email - has already been taken')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response body structure, report it via GitHub issues', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $reason = str_contains(strtolower($e->getMessage()), 'timed out') ? 'Connection timed out! maybe region blocks' : $e->getMessage();
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
