<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class CrazygamesValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'crazygames';
    }

    public function category(): string
    {
        return 'gaming';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Crazygames';
    }

    public function siteUrl(): string
    {
        return 'https://crazygames.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(20)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36',
                'Content-Type' => 'application/json',
                'origin' => 'https://www.crazygames.com',
                'referer' => 'https://www.crazygames.com/',
                'Accept-Encoding' => 'identity',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request
                ->withBody(json_encode([
                    'identifier' => $target,
                    'continueUri' => 'https://www.crazygames.com/',
                ], JSON_THROW_ON_ERROR), 'application/json')
                ->post('https://identitytoolkit.googleapis.com/v1/accounts:createAuthUri?key=AIzaSyAkBGn9sKEUBSMQ9CTFyHHxXas0tdcpts8');

            $isRegistered = $response->json('registered');
            if ($isRegistered === true) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if ($isRegistered === false) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response body, report it on github', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $reason = str_contains(strtolower($e->getMessage()), 'timed out')
                ? 'Connection timed out'
                : $e->getMessage();

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
