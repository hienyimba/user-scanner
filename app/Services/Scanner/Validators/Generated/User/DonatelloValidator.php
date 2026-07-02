<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class DonatelloValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'donatello';
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
        return 'Donatello';
    }

    public function siteUrl(): string
    {
        return 'https://donatello.to';
    }

    protected function requestUrl(string $target): string
    {
        return "https://donatello.to/{$target}";
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
