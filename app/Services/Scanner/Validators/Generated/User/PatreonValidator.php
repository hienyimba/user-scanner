<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class PatreonValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'patreon'; }
    public function category(): string { return 'creator'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Patreon'; }
    public function siteUrl(): string { return 'https://www.patreon.com'; }
    protected function timeoutSeconds(): int { return 20; }
    protected function requestUrl(string $target): string { return "https://www.patreon.com/{$target}"; }
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return match ($response->status()) {
            404 => ['Available', ''],
            200 => ['Taken', ''],
            default => ['Error', 'HTTP ' . $response->status()],
        };
    }
}
