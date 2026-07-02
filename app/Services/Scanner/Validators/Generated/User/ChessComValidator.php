<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ChessComValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'chess_com';
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
        return 'ChessCom';
    }

    public function siteUrl(): string
    {
        return 'https://www.chess.com';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://www.chess.com/callback/user/valid';
    }

    protected function requestQuery(string $target): array
    {
        return ['username' => $target];
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'identity',
            'Accept-Language' => 'en-US,en;q=0.9',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() !== 200) {
            return ['Error', 'Invalid status code'];
        }

        $data = $response->json();
        if (($data['valid'] ?? null) === true) {
            return ['Available', ''];
        }

        if (($data['valid'] ?? null) === false) {
            return ['Taken', ''];
        }

        return ['Error', 'Invalid status code'];
    }
}
