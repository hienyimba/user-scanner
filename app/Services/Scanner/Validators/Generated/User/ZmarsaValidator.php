<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ZmarsaValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'zmarsa'; }
    public function category(): string { return 'adult'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Zmarsa'; }
    public function siteUrl(): string { return 'https://zmarsa.com/uzytkownik'; }
    protected function requestUrl(string $target): string { return "https://zmarsa.com/uzytkownik/{$target}"; }
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $body = $response->body();
        if (str_contains($body, 'Statystyki')) {
            return ['Taken', ''];
        }
        if (str_contains($body, '<title>Error 404 - zMarsa.com<')) {
            return ['Available', ''];
        }
        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
