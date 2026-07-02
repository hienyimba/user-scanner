<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BattlenetValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'battlenet';
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
        return 'Battlenet';
    }

    public function siteUrl(): string
    {
        return 'https://overwatch.blizzard.com';
    }

    protected function requestUrl(string $target): string
    {
        $username = explode('#', $target)[0];

        return 'https://overwatch.blizzard.com/en-us/search/account-by-name/' . urlencode($username);
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Accept-Encoding' => 'gzip, deflate, br, zstd',
        ];
    }

    protected function timeoutSeconds(): int
    {
        return 6;
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() !== 200) {
            return ['Error', 'Unexpected status: ' . $response->status()];
        }

        $data = $response->json();
        if (!is_array($data)) {
            return ['Error', 'Failed to parse response'];
        }

        if ($data === []) {
            return ['Available', 'Battle.net allows duplicate usernames and distinguishes accounts with a numeric tag'];
        }

        return ['Taken', count($data) . ' match' . (count($data) > 1 ? 'es' : '')];
    }
}
