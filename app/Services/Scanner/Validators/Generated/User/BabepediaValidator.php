<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BabepediaValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'babepedia'; }
    public function category(): string { return 'adult'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Babepedia'; }
    public function siteUrl(): string { return 'https://www.babepedia.com/user'; }
    protected function requestUrl(string $target): string { return "https://www.babepedia.com/user/{$target}"; }
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();
        if ($status === 200 && str_contains($body, "'s Profile</title>")) {
            return ['Taken', ''];
        }
        if ($status === 404 || str_contains($body, 'Profile not found')) {
            return ['Available', ''];
        }
        return ['Error', 'Unexpected response body, report it via GitHub issues'];
    }
}
