<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class NeocitiesValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'neocities';
    }

    public function category(): string
    {
        return 'hosting';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Neocities';
    }

    public function siteUrl(): string
    {
        return 'https://neocities.org';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(10)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Encoding' => 'identity',
                'sec-ch-ua-platform' => '"Android"',
                'x-requested-with' => 'XMLHttpRequest',
                'origin' => 'https://neocities.org',
                'referer' => 'https://neocities.org/',
                'accept-language' => 'en-US,en;q=0.9',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->asForm()->post('https://neocities.org/create_validate', [
                'field' => 'email',
                'value' => $target,
                'is_education' => 'false',
            ]);

            if ($response->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF or IP Block (403)', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited wait for few minutes', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'HTTP Error: ' . $response->status(), mode: $this->mode(), key: $this->key());
            }

            $data = $response->json();
            if (is_array($data) && array_key_exists('error', $data)) {
                $error = (string) $data['error'];
                if (str_contains(strtolower($error), 'already exists')) {
                    return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
                }
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Neocities error: ' . $error, mode: $this->mode(), key: $this->key());
            }
            if (is_array($data) && (($data['result'] ?? null) === 'ok')) {
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
