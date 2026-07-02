<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class LinktreeValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'linktree'; }
    public function category(): string { return 'creator'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Linktree'; }
    public function siteUrl(): string { return 'https://linktr.ee'; }
    protected function requestUrl(string $target): string { return "https://linktr.ee/{$target}"; }
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br, zstd',
        ];
    }
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return match ($response->status()) {
            404 => ['Available', ''],
            200 => ['Taken', ''],
            default => ['Error', 'HTTP ' . $response->status()],
        };
    }
}
