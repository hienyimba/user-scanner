<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class MediumValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'medium'; }
    public function category(): string { return 'creator'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Medium'; }
    public function siteUrl(): string { return 'https://medium.com/@'; }
    protected function requestUrl(string $target): string { return "https://medium.com/@{$target}"; }
    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Encoding' => 'identity',
            'upgrade-insecure-requests' => '1',
            'sec-fetch-site' => 'none',
            'sec-fetch-mode' => 'navigate',
            'sec-fetch-user' => '?1',
            'sec-fetch-dest' => 'document',
            'sec-ch-ua' => '"Google Chrome";v="143", "Chromium";v="143", "Not A(Brand";v="24"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'accept-language' => 'en-US,en;q=0.9',
            'priority' => 'u=0, i',
            'cache-control' => 'max-age=0',
        ];
    }
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() === 200) {
            $usernameTag = 'property="profile:username" content="' . $target . '"';
            return str_contains($response->body(), $usernameTag) ? ['Taken', ''] : ['Available', ''];
        }
        return ['Error', 'Unexpected status: ' . $response->status()];
    }
}
