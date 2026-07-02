<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BlueskyValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'bluesky';
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
        return 'Bluesky';
    }

    public function siteUrl(): string
    {
        return 'https://bsky.app/profile/.bsky.social';
    }

    protected function timeoutSeconds(): int
    {
        return 15;
    }

    protected function requestUrl(string $target): string
    {
        return 'https://bsky.social/xrpc/com.atproto.temp.checkHandleAvailability';
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept-Encoding' => 'gzip',
            'atproto-accept-labelers' => 'did:plc:ar7c4by46qjdydhdevvrndac;redact',
            'sec-ch-ua-platform' => '"Android"',
            'sec-ch-ua' => '"Google Chrome";v="141", "Not?A_Brand";v="8", "Chromium";v="141"',
            'sec-ch-ua-mobile' => '?1',
            'origin' => 'https://bsky.app',
            'sec-fetch-site' => 'cross-site',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-dest' => 'empty',
            'referer' => 'https://bsky.app/',
            'accept-language' => 'en-US,en;q=0.9',
        ];
    }

    protected function requestQuery(string $target): array
    {
        return [
            'handle' => str_ends_with($target, '.bsky.social') ? $target : $target . '.bsky.social',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() === 200) {
            $data = $response->json();
            $resultType = $data['result']['$type'] ?? null;

            if ($resultType === 'com.atproto.temp.checkHandleAvailability#resultAvailable') {
                return ['Available', ''];
            }

            if ($resultType === 'com.atproto.temp.checkHandleAvailability#resultUnavailable') {
                return ['Taken', ''];
            }
        }

        if ($response->status() === 400) {
            return ['Error', 'Username can only contain letters, numbers, hyphens (no leading/trailing)'];
        }

        return ['Error', 'Invalid status code!'];
    }
}
