<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class AnilistValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'anilist';
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
        return 'Anilist';
    }

    public function siteUrl(): string
    {
        return 'https://anilist.co/user';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://graphql.anilist.co';
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    protected function requestBody(string $target): array
    {
        return [
            'query' => 'query{User(name:"' . $target . '"){id name}}',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();

        if ($status === 200 && str_contains($body, '"id":')) {
            return ['Taken', ''];
        }

        if ($status === 404 || str_contains($body, 'Not Found')) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected status: ' . $status];
    }
}
