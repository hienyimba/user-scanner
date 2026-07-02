<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class OsuValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'osu';
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
        return 'Osu';
    }

    public function siteUrl(): string
    {
        return 'https://osu.ppy.sh';
    }

    protected function requestUrl(string $target): string
    {
        return "https://osu.ppy.sh/users/{$target}";
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return match ($response->status()) {
            404 => ['Available', ''],
            200, 302 => ['Taken', ''],
            401, 403, 429 => ['Error', $this->key() . ': blocked/rate-limited (HTTP ' . $response->status() . ')'],
            default => ['Error', $this->key() . ': indeterminate username response (HTTP ' . $response->status() . ')'],
        };
    }
}
