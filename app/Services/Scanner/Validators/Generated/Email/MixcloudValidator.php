<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class MixcloudValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'mixcloud';
    }

    public function category(): string
    {
        return 'music';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Mixcloud';
    }

    public function siteUrl(): string
    {
        return 'https://mixcloud.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(7)->withOptions([
                'allow_redirects' => true,
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Encoding' => 'identity',
                'x-requested-with' => 'XMLHttpRequest',
                'x-mixcloud-platform' => 'www',
                'origin' => 'https://www.mixcloud.com',
                'referer' => 'https://www.mixcloud.com/',
                'accept-language' => 'en-US,en;q=0.9',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->asForm()->post('https://app.mixcloud.com/authentication/email-register/', [
                'email' => $target,
                'username' => 'you_ar3_al0n3_fight',
                'password' => '',
                'ch' => 'y',
            ]);

            $status = $response->status();
            if ($status === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by WAF or IP Block (403)', mode: $this->mode(), key: $this->key());
            }
            if ($status === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited by Mixcloud', mode: $this->mode(), key: $this->key());
            }
            if ($status === 200) {
                $emailErrors = $response->json('data.$errors.email') ?? [];
                if (is_array($emailErrors)) {
                    foreach ($emailErrors as $error) {
                        if (str_contains(strtolower((string) $error), 'already in use')) {
                            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
                        }
                    }
                }
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected status code: ' . $status, mode: $this->mode(), key: $this->key());
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
