<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ArchwikiValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'archwiki';
    }

    public function category(): string
    {
        return 'community';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Archwiki';
    }

    public function siteUrl(): string
    {
        return 'https://wiki.archlinux.org/title/User:';
    }

    protected function requestUrl(string $target): string
    {
        return "https://wiki.archlinux.org/api.php?action=query&format=json&list=users&ususers={$target}&usprop=cancreate&formatversion=2";
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();

        if (str_contains($body, '"userid":')) {
            return ['Taken', ''];
        }

        if (str_contains($body, '"missing":true')) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected status: ' . $status];
    }
}
