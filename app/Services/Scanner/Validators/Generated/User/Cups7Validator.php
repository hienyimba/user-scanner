<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class Cups7Validator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'cups7';
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
        return 'Cups7';
    }

    public function siteUrl(): string
    {
        return 'https://www.7cups.com/@';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.7cups.com/@{$target}";
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $body = $response->body();

        if (str_contains($body, 'Profile - 7 Cups')) {
            return ['Taken', ''];
        }

        if (str_contains($body, "The content you're attempting to access could not be")) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
