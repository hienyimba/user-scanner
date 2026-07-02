<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class RedditValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'reddit';
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
        return 'Reddit';
    }

    public function siteUrl(): string
    {
        return 'https://www.reddit.com/user';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.reddit.com/user/{$target}/about.json";
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status === 429) {
            return ['Error', 'Rate limit exceeded'];
        }

        if ($status === 200) {
            try {
                $data = $response->json();
            } catch (\Throwable) {
                return ['Error', 'Malformed JSON response, report it on Github'];
            }

            if (($data['error'] ?? null) === 404 || ($data['message'] ?? null) === 'Not Found') {
                return ['Available', ''];
            }

            if (($data['kind'] ?? null) === 't2' || array_key_exists('data', $data)) {
                return ['Taken', ''];
            }
        }

        return ['Error', 'HTTP ' . $status];
    }
}
