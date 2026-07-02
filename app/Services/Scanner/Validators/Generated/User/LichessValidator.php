<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class LichessValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'lichess';
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
        return 'Lichess';
    }

    public function siteUrl(): string
    {
        return 'https://lichess.org';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://lichess.org/api/player/autocomplete';
    }

    protected function requestQuery(string $target): array
    {
        return [
            'term' => $target,
            'exists' => 1,
        ];
    }

    protected function timeoutSeconds(): int
    {
        return 3;
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $body = strtolower(trim($response->body()));

        if ($body === 'true') {
            return ['Taken', ''];
        }

        if ($body === 'false') {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected error, report it via github issues'];
    }
}
