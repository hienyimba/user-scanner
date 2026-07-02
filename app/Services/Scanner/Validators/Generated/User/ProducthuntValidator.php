<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ProducthuntValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'producthunt'; }
    public function category(): string { return 'creator'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Producthunt'; }
    public function siteUrl(): string { return 'https://www.producthunt.com/@'; }
    protected function requestUrl(string $target): string { return "https://www.producthunt.com/@{$target}"; }
    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept-Language' => 'en-US,en;q=0.9',
            'upgrade-insecure-requests' => '1',
            'sec-fetch-site' => 'none',
            'sec-fetch-mode' => 'navigate',
            'sec-fetch-user' => '?1',
            'sec-fetch-dest' => 'document',
            'sec-ch-ua' => '"Google Chrome";v="143", "Chromium";v="143", "Not A(Brand";v="24"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'cache-control' => 'max-age=0',
        ];
    }
    public function check(string $target, array $options = []): ScanResult
    {
        if (strlen($target) < 2 || strlen($target) > 32) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Length must be 2-32 characters.', mode: $this->mode(), key: $this->key());
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $target)) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Only use letters, numbers, and underscores.', mode: $this->mode(), key: $this->key());
        }
        return parent::check($target, $options);
    }
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return match ($response->status()) {
            404 => ['Available', ''],
            200 => ['Taken', ''],
            default => ['Error', 'HTTP ' . $response->status()],
        };
    }
}
