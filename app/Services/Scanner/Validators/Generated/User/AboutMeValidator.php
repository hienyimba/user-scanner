<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class AboutMeValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'about_me';
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
        return 'AboutMe';
    }

    public function siteUrl(): string
    {
        return 'https://about.me';
    }

    protected function requestUrl(string $target): string
    {
        return "https://about.me/{$target}";
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $body = $response->body();

        if (str_contains($body, ' | about.me')) {
            return ['Taken', ''];
        }

        if (str_contains($body, '<title>about.me</title>')) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
