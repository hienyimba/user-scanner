<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Support\Facades\Http;

final class NaturabuyValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'naturabuy';
    }

    public function category(): string
    {
        return 'shopping';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Naturabuy';
    }

    public function siteUrl(): string
    {
        return 'https://naturabuy.fr';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(10)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                'Accept' => '*/*',
                'Accept-Language' => 'fr,fr-FR;q=0.9,en;q=0.8',
                'X-Requested-With' => 'XMLHttpRequest',
                'Origin' => 'https://www.naturabuy.fr',
                'Referer' => 'https://www.naturabuy.fr/register.php',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->asMultipart()->post('https://www.naturabuy.fr/includes/ajax/register.php', [
                ['name' => 'jsref', 'contents' => 'email'],
                ['name' => 'jsvalue', 'contents' => $target],
                ['name' => 'registerMode', 'contents' => 'full'],
            ]);

            if ($response->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected status: ' . $response->status(), mode: $this->mode(), key: $this->key());
            }

            $free = $response->json('free');
            if ($free === false) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if ($free === true) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response format', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $reason = str_contains(strtolower($e->getMessage()), 'timed out')
                ? 'Connection timed out'
                : $e->getMessage();

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }
}
