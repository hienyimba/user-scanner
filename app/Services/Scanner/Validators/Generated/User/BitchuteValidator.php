<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BitchuteValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'bitchute';
    }

    public function category(): string
    {
        return 'political';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Bitchute';
    }

    public function siteUrl(): string
    {
        return 'https://www.bitchute.com/channel';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.bitchute.com/api/beta/channel";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function requestHeaders(): array
    {
        return [
            // Python parity.
            'Content-Type' => 'application/json',
        ];
    }

    /** @return array<string,mixed> */
    protected function requestBody(string $target): array
    {
        // Python payload = {"channel_id": user}
        return [
            'channel_id' => $target,
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $body = $response->body();

        // Python parity: explicit body markers (no generic anti-bot shortcut).
        if (str_contains($body, '"channel_id":')) {
            return ['Taken', ''];
        }

        if (str_contains($body, '"errors":')) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
