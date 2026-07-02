<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Support\Facades\Http;

final class BootDevValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'boot_dev';
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
        return 'BootDev';
    }

    public function siteUrl(): string
    {
        return 'https://boot.dev';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(5)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->withHeaders([
                    'Accept' => 'application/json, text/plain, */*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0',
                    'Referer' => 'https://boot.dev/',
                    'Content-Type' => 'application/json',
                    'Origin' => 'https://boot.dev',
                    'Accept-Encoding' => 'identity',
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $payload = json_encode(['email' => $target], JSON_THROW_ON_ERROR);
            $response = null;
            foreach ([
                'https://api.boot.dev/v1/users/email/exists',
                'https://boot.dev/api/v1/users/email/exists',
                'https://www.boot.dev/api/v1/users/email/exists',
                'https://api.boot.dev/users/email/exists',
            ] as $url) {
                $attempt = $request->withBody($payload, 'application/json')->post($url);
                if ($attempt->status() !== 404) {
                    $response = $attempt;
                    break;
                }
                $response = $attempt;
            }

            if ($response !== null && $response->status() === 200) {
                $data = $response->json();
                $exists = $data['Exists'] ?? null;

                if ($exists === true) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
                }

                if ($exists === false) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
                }
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'HTTP ' . ($response?->status() ?? 0), mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $reason = str_contains(strtolower($e->getMessage()), 'timed out') ? 'Connection timed out' : $e->getMessage();
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(\Illuminate\Http\Client\Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
