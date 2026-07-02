<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BandcampValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'bandcamp';
    }

    public function category(): string
    {
        return 'music';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Bandcamp';
    }

    public function siteUrl(): string
    {
        return 'https://bandcamp.com';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://bandcamp.com/{$target}";
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
            // No connector-specific headers inferred.
        ];
    }
    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();

        // Python parity: bandcamp uses explicit status/body markers (no generic anti-bot shortcut).
        if ($status === 200 && str_contains($body, ' collection | Bandcamp</title>')) {
            return ['Taken', ''];
        }

        if (
            $status === 404
            || str_contains($body, "<h2>Sorry, that something isnâ€™t here.</h2>")
            || str_contains($body, "<h2>Sorry, that something isn't here.</h2>")
            || str_contains($body, "<h2>Sorry, that something isn’t here.</h2>")
            || str_contains($body, "<h2>Sorry, that something isn???t here.</h2>")
        ) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
