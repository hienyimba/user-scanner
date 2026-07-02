<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class CodewarsValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'codewars';
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
        return 'Codewars';
    }

    public function siteUrl(): string
    {
        return 'https://codewars.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(7)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                    'Origin' => 'https://www.codewars.com',
                    'Referer' => 'https://www.codewars.com/join',
                    'Upgrade-Insecure-Requests' => '1',
                    'Accept-Encoding' => 'identity',
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->asForm()->post('https://www.codewars.com/join?language=javascript', [
                'utf8' => '✓',
                '_method' => 'patch',
                'user[email]' => $target,
                'user[username]' => '',
                'user[password]' => '',
                'user[password_confirmation]' => '',
                'utm[source]' => '',
                'utm[medium]' => '',
                'utm[campaign]' => '',
                'utm[term]' => '',
                'utm[content]' => '',
                'utm[referrer]' => 'https://www.google.com/',
            ]);

            $html = $response->body();
            if (str_contains($html, 'is already taken')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (str_contains($html, 'can&#39;t be blank')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response pattern', mode: $this->mode(), key: $this->key());
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
