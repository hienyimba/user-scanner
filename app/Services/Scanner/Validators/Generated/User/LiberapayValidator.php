<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class LiberapayValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'liberapay';
    }

    public function category(): string
    {
        return 'donation';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Liberapay';
    }

    public function siteUrl(): string
    {
        return 'https://en.liberapay.com';
    }

    protected function requestUrl(string $target): string
    {
        return "https://en.liberapay.com/{$target}";
    }

    protected function requestHeaders(): array
    {
        return [
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'accept-language' => 'en-Us,pt;q=0.6',
            'cache-control' => 'no-cache',
            'pragma' => 'no-cache',
            'priority' => 'u=0, i',
            'sec-ch-ua' => '"Chromium";v="142", "Brave";v="142", "Not_A Brand";v="99"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'sec-fetch-dest' => 'document',
            'sec-fetch-mode' => 'navigate',
            'sec-fetch-site' => 'none',
            'sec-fetch-user' => '?1',
            'sec-gpc' => '1',
            'upgrade-insecure-requests' => '1',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return match ($response->status()) {
            404 => ['Available', ''],
            200 => ['Taken', ''],
            default => ['Error', 'HTTP ' . $response->status()],
        };
    }
}
