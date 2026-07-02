<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ApexlegendsValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'apexlegends';
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
        return 'Apexlegends';
    }

    public function siteUrl(): string
    {
        return 'https://apex.tracker.gg';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.tracker.gg/api/v2/apex/standard/profile/origin/{$target}";
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept-Language' => 'en-US,en;q=0.5',
            'Origin' => 'https://apex.tracker.gg',
            'Referer' => 'https://apex.tracker.gg/',
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return match ($response->status()) {
            404 => ['Available', ''],
            200 => ['Taken', ''],
            401, 403, 429 => ['Error', $this->key() . ': blocked/rate-limited (HTTP ' . $response->status() . ')'],
            default => ['Error', $this->key() . ': indeterminate username response (HTTP ' . $response->status() . ')'],
        };
    }
}
