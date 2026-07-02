<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class PinterestValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'pinterest';
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
        return 'Pinterest';
    }

    public function siteUrl(): string
    {
        return 'https://www.pinterest.com';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.pinterest.com/{$target}/";
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() === 200) {
            if (str_contains($response->body(), 'User not found.')) {
                return ['Available', ''];
            }

            return ['Taken', ''];
        }

        return ['Error', 'Invalid status code'];
    }
}
