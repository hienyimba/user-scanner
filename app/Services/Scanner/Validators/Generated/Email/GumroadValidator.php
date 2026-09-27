<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class GumroadValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'gumroad';
    }

    public function category(): string
    {
        return 'creator';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Gumroad';
    }

    public function siteUrl(): string
    {
        return 'https://gumroad.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $showUrl = $this->siteUrl();
        $signupUrl = 'https://gumroad.com/signup';

        try {
            $client = Http::timeout(15)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                    'cookies' => new CookieJar,
                ]);

            if (! empty($options['proxy'])) {
                $client = $client->withOptions(['proxy' => $options['proxy']]);
            }

            $page = $client->withHeaders([
                'User-Agent' => (string) config('scanner.user_agent'),
                'Accept' => 'text/html,application/xhtml+xml',
            ])->get($signupUrl);

            if ($page->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Unexpected Gumroad signup response: '.$page->status(), mode: $this->mode(), key: $this->key());
            }

            if (preg_match('/<meta name="csrf-token" content="([^"]+)"/', $page->body(), $csrfMatch) !== 1
                || (! str_contains($page->body(), 'Signup\/New')
                    && ! str_contains($page->body(), 'Signup/New'))) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Could not read Gumroad signup form', mode: $this->mode(), key: $this->key());
            }

            $response = $client
                ->withHeaders([
                    'Accept' => 'text/html, application/xhtml+xml',
                    'Origin' => 'https://gumroad.com',
                    'Referer' => $signupUrl,
                    'X-CSRF-Token' => html_entity_decode($csrfMatch[1], ENT_QUOTES | ENT_HTML5),
                    'X-Inertia' => 'true',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->post($signupUrl, [
                    'user' => [
                        'email' => $target,
                        'password' => 'Q7~',
                    ],
                ]);

            if ($response->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Unexpected Gumroad signup response: '.$response->status(), mode: $this->mode(), key: $this->key());
            }

            $data = $response->json();
            if (($data['component'] ?? null) !== 'Signup/New') {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Unexpected Gumroad signup page', mode: $this->mode(), key: $this->key());
            }

            $message = data_get($data, 'props.flash.message');
            if ($message === 'An account already exists with this email.') {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if ($message === 'Password is too short (minimum is 4 characters)') {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Unexpected Gumroad signup result', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'unexpected exception: '.$e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Gumroad uses a custom CSRF flow'];
    }
}
