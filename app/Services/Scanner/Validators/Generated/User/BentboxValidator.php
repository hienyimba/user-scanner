<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BentboxValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'bentbox'; }
    public function category(): string { return 'adult'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Bentbox'; }
    public function siteUrl(): string { return 'https://bentbox.co'; }
    protected function requestUrl(string $target): string { return "https://bentbox.co/{$target}"; }
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $body = $response->body();
        if ($response->status() === 200 && str_contains($body, 'user_bar')) {
            return ['Taken', ''];
        }
        if (str_contains($body, 'user is currently not available')) {
            return ['Available', ''];
        }
        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
