<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class AmericanthinkerValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'americanthinker';
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
        return 'Americanthinker';
    }

    public function siteUrl(): string
    {
        return 'https://www.americanthinker.com/author';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.americanthinker.com/author/{$target}/";
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

        // Python parity: status_validate(url, available=404, taken=200)
        if ($status === 404) {
            return ['Available', ''];
        }
        if ($status === 200) {
            return ['Taken', ''];
        }

        return ['Error', '[' . $status . "] Status didn't match. Report this on Github."];
    }
}
