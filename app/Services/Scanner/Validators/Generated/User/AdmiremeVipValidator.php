<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class AdmiremeVipValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'admireme_vip'; }
    public function category(): string { return 'adult'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'AdmiremeVip'; }
    public function siteUrl(): string { return 'https://admireme.vip'; }
    protected function requestUrl(string $target): string { return "https://admireme.vip/{$target}/"; }
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ];
    }
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();
        if (str_contains($body, 'creator-stat subscriber')) {
            return ['Taken', ''];
        }
        if ($status === 404 || str_contains($body, '<title>Page Not Found |')) {
            return ['Available', ''];
        }
        return ['Error', 'Unexpected status: ' . $status];
    }
}
