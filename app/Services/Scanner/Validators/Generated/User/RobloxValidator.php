<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class RobloxValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'roblox';
    }

    public function category(): string
    {
        return 'gaming';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Roblox';
    }

    public function siteUrl(): string
    {
        return 'https://roblox.com';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://users.roblox.com/v1/users/search';
    }

    protected function requestQuery(string $target): array
    {
        return [
            'keyword' => $target,
            'limit' => 10,
        ];
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $first = parent::check($target, $options);
        if ($first->reason !== 'Too many requests') {
            return $first;
        }

        try {
            $request = Http::timeout(10)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->get('https://www.roblox.com/user.aspx', ['username' => $target]);

            $status = match ($response->status()) {
                404 => 'Available',
                200, 302 => 'Taken',
                default => 'Error',
            };
            $reason = $status === 'Error' ? 'Invalid status code' : '';

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), $status, $reason, mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $data = $response->json();

        if ($response->status() === 429) {
            return ['Error', 'Too many requests'];
        }

        if ($response->status() === 400) {
            $errorCode = $data['errors'][0]['code'] ?? null;
            return match ($errorCode) {
                6 => ['Error', 'Username is too short'],
                5 => ['Error', 'Username was filtered'],
                default => ['Error', 'Invalid username'],
            };
        }

        foreach (($data['data'] ?? []) as $entry) {
            if (strtolower((string) ($entry['name'] ?? '')) === strtolower($target)) {
                return ['Taken', ''];
            }
        }

        return ['Available', ''];
    }
}
