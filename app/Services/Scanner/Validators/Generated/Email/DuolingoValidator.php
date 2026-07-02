<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class DuolingoValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'duolingo';
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
        return 'Duolingo';
    }

    public function siteUrl(): string
    {
        return 'https://duolingo.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(5)->withOptions([
                'allow_redirects' => true,
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'authority' => 'www.duolingo.com',
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Language' => 'en-US,en;q=0.9',
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0',
                'Referer' => 'https://www.duolingo.com/',
                'Accept-Encoding' => 'identity',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->get('https://www.duolingo.com/2017-06-30/users', [
                'email' => $target,
            ]);

            if ($response->status() === 200) {
                $users = $response->json('users');
                if (is_array($users) && count($users) > 0) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
                }
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'HTTP ' . $response->status(), mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $reason = str_contains(strtolower($e->getMessage()), 'timed out') ? 'Connection timed out' : $e->getMessage();
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
