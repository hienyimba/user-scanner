<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class Site7dachValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return '7dach';
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
        return '7dach';
    }

    public function siteUrl(): string
    {
        return 'https://7dach.ru/profile';
    }

    protected function requestUrl(string $target): string
    {
        return "https://7dach.ru/profile/{$target}";
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $body = $response->body();

        if (str_contains($body, 'Информация / Профиль')) {
            return ['Taken', ''];
        }

        if (str_contains($body, '<title>Ошибка / 7dach.ru')) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
