<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class InstagramValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'instagram';
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
        return 'Instagram';
    }

    public function siteUrl(): string
    {
        return 'https://www.instagram.com';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://www.instagram.com/api/v1/users/web_profile_info/';
    }

    protected function requestQuery(string $target): array
    {
        return [
            'username' => $target,
        ];
    }

    protected function requestHeadersForTarget(string $target): array
    {
        return [
            'Accept-Encoding' => 'gzip, deflate, br, zstd',
            'sec-ch-ua-full-version-list' => '"Not(A:Brand";v="8.0.0.0", "Chromium";v="144.0.7559.132", "Google Chrome";v="144.0.7559.132"',
            'sec-ch-ua-platform' => '"Linux"',
            'sec-ch-ua' => '"Not(A:Brand";v="8", "Chromium";v="144", "Google Chrome";v="144"',
            'sec-ch-ua-model' => '""',
            'sec-ch-ua-mobile' => '?0',
            'x-ig-app-id' => '936619743392459',
            'x-requested-with' => 'XMLHttpRequest',
            'sec-ch-prefers-color-scheme' => 'dark',
            'x-ig-www-claim' => '0',
            'sec-ch-ua-platform-version' => '""',
            'sec-fetch-site' => 'same-origin',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-dest' => 'empty',
            'referer' => "https://www.instagram.com/{$target}",
            'accept-language' => 'en-US,en;q=0.9',
            'priority' => 'u=1, i',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status === 200) {
            return ['Taken', ''];
        }

        return ['Error', $this->key() . ': blocked/rate-limited (HTTP ' . $status . ')'];
    }
}
