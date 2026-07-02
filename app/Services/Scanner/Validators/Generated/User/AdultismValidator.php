<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class AdultismValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'adultism'; }
    public function category(): string { return 'adult'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Adultism'; }
    public function siteUrl(): string { return 'https://www.adultism.com/profile'; }
    protected function requestUrl(string $target): string { return "https://www.adultism.com/profile/{$target}"; }
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return match ($response->status()) {
            404 => ['Available', ''],
            200 => ['Taken', ''],
            default => ['Error', 'HTTP ' . $response->status()],
        };
    }
}
