<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class AllenValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'allen';
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
        return 'Allen';
    }

    public function siteUrl(): string
    {
        return 'https://allen.in';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(10)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36',
                'Accept' => 'application/json',
                'Accept-Encoding' => 'identity',
                'x-client-type' => 'mweb',
                'Origin' => 'https://allen.in',
                'Referer' => 'https://allen.in/',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->get("https://api.allen-live.in/api/v1/user/identities/{$target}", [
                'communicable' => 'true',
                'identity_type' => 'EMAIL',
            ]);

            if ($response->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Blocked by Allen WAF (403)', mode: $this->mode(), key: $this->key());
            }

            $data = $response->json();
            $reason = (string) ($data['reason'] ?? '');

            if (($data['status'] ?? null) === 200 && $reason === 'OK') {
                $identities = is_array($data['data']['identities'] ?? null) ? $data['data']['identities'] : [];
                $maskedPhone = null;
                foreach ($identities as $item) {
                    if (($item['identity_type'] ?? null) === 'PHONE') {
                        $maskedPhone = (string) ($item['identity_value'] ?? '');
                        break;
                    }
                }

                $extra = $maskedPhone !== null && $maskedPhone !== '' ? 'Phone: +91' . $maskedPhone : null;
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', $extra, mode: $this->mode(), key: $this->key());
            }
            if (str_contains($reason, 'Invalid email')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unknown response reason: ' . $reason, mode: $this->mode(), key: $this->key());
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
