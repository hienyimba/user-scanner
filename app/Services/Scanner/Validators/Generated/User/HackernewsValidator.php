<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class HackernewsValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'hackernews';
    }

    public function category(): string
    {
        return 'community';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Hackernews';
    }

    public function siteUrl(): string
    {
        return 'https://news.ycombinator.com/user?id=';
    }

    protected function requestUrl(string $target): string
    {
        return "https://news.ycombinator.com/user?id={$target}";
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = strtolower($response->body());

        if (str_contains($body, 'no such user.')) {
            return ['Available', ''];
        }

        if ($status === 200) {
            return ['Taken', ''];
        }

        return ['Error', 'Unexpected status: ' . $status];
    }
}
