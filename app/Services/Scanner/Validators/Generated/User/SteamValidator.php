<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class SteamValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'steam';
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
        return 'Steam';
    }

    public function siteUrl(): string
    {
        return 'https://steamcommunity.com/id';
    }

    protected function requestUrl(string $target): string
    {
        return "https://steamcommunity.com/id/{$target}/";
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() !== 200) {
            return ['Error', 'Invalid status code'];
        }

        if (str_contains($response->body(), 'Error</title>')) {
            return ['Available', ''];
        }

        return ['Taken', ''];
    }
}
