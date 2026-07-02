<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class AdultforumValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'adultforum'; }
    public function category(): string { return 'adult'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Adultforum'; }
    public function siteUrl(): string { return 'https://adultforum.gr/-glamour-escorts'; }
    protected function requestUrl(string $target): string { return "https://adultforum.gr/{$target}-glamour-escorts/"; }
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
        if (str_contains($body, 'Glamour Escorts ')) {
            return ['Taken', ''];
        }
        if ($status === 404 || str_contains($body, 'Page not found - Adult Forum Gr')) {
            return ['Available', ''];
        }
        return ['Error', 'Unexpected status: ' . $status];
    }
}
