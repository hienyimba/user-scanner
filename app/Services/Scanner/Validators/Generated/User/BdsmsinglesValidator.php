<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BdsmsinglesValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'bdsmsingles'; }
    public function category(): string { return 'adult'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Bdsmsingles'; }
    public function siteUrl(): string { return 'https://www.bdsmsingles.com/members'; }
    protected function requestUrl(string $target): string { return "https://www.bdsmsingles.com/members/{$target}/"; }
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();
        if ($status === 200 && str_contains($body, '<title>Profile')) {
            return ['Taken', ''];
        }
        if ($status === 302 || str_contains($body, 'BDSM Singles')) {
            return ['Available', ''];
        }
        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
