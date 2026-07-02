<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class DiscordValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'discord';
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
        return 'Discord';
    }

    public function siteUrl(): string
    {
        return 'https://discord.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function timeoutSeconds(): int
    {
        return 3;
    }

    protected function requestUrl(string $target): string
    {
        return 'https://discord.com/api/v9/unique-username/username-attempt-unauthed';
    }

    protected function requestHeaders(): array
    {
        return [
            'authority' => 'discord.com',
            'accept' => '/',
            'accept-language' => 'en-GB,en-US;q=0.9,en;q=0.8',
            'content-type' => 'application/json',
            'origin' => 'https://discord.com',
            'referer' => 'https://discord.com/register',
        ];
    }

    protected function requestBody(string $target): array
    {
        return [
            'username' => $target,
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() === 200) {
            $taken = $response->json('taken');
            if ($taken === true) {
                return ['Taken', ''];
            }
            if ($taken === false) {
                return ['Available', ''];
            }
        }

        return ['Error', 'Invalid status code'];
    }
}
