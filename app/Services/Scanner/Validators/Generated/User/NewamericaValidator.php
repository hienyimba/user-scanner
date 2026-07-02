<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class NewamericaValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'newamerica';
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
        return 'Newamerica';
    }

    public function siteUrl(): string
    {
        return 'https://www.newamerica.org/our-people';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.newamerica.org/our-people/{$target}/";
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
        $body = $response->body();

        // Python parity: explicit body markers (no generic anti-bot shortcut).
        if (str_contains($body, 'href="http://newamerica.org/our-people/' . $target . '/"')) {
            return ['Taken', ''];
        }

        if (str_contains($body, 'Page not found')) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
