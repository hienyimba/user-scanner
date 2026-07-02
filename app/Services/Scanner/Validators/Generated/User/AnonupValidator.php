<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class AnonupValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'anonup';
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
        return 'Anonup';
    }

    public function siteUrl(): string
    {
        return 'https://anonup.com/@';
    }

    protected function requestUrl(string $target): string
    {
        return "https://anonup.com/@{$target}";
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();

        if (str_contains($body, 'Show followings')) {
            return ['Taken', ''];
        }

        if (str_contains($body, 'Page not found!') || $status === 302) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body!'];
    }
}
