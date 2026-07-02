<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class AmebloValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'ameblo'; }
    public function category(): string { return 'creator'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Ameblo'; }
    public function siteUrl(): string { return 'https://ameblo.jp'; }
    protected function requestUrl(string $target): string { return "https://ameblo.jp/{$target}"; }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return match ($response->status()) {
            404 => ['Available', ''],
            200 => ['Taken', ''],
            default => ['Error', 'HTTP ' . $response->status()],
        };
    }
}
