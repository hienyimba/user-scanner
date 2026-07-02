<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class AlbiclaValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'albicla';
    }

    public function category(): string
    {
        return 'social';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Albicla';
    }

    public function siteUrl(): string
    {
        return 'https://albicla.com';
    }

    protected function requestUrl(string $target): string
    {
        return "https://albicla.com/{$target}/post/1";
    }

    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => config('scanner.user_agent'),
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();

        if ($status === 500 || str_contains($body, '500 Post tymczasowo niedostępny')) {
            return ['Taken', ''];
        }

        if (str_contains($body, '404 Nie znaleziono użytkownika')) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected status: ' . $status];
    }
}
