<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BdsmlrValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'bdsmlr'; }
    public function category(): string { return 'adult'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Bdsmlr'; }
    public function siteUrl(): string { return 'https://.bdsmlr.com'; }
    protected function requestUrl(string $target): string { return 'https://' . str_replace('.', '', $target) . '.bdsmlr.com'; }
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $body = $response->body();
        if ($response->status() === 200 && str_contains($body, 'login')) {
            return ['Taken', ''];
        }
        if (str_contains($body, "This blog doesn't exist.")) {
            return ['Available', ''];
        }
        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
