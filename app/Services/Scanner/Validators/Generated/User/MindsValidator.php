<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class MindsValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'minds';
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
        return 'Minds';
    }

    public function siteUrl(): string
    {
        return 'https://www.minds.com';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.minds.com/api/v3/register/validate?username={$target}";
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $body = $response->body();

        if ($response->status() === 200 && str_contains($body, '"valid":false')) {
            return ['Taken', ''];
        }

        if (str_contains($body, '"valid":true')) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
