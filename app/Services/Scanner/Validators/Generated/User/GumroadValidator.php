<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class GumroadValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'gumroad'; }
    public function category(): string { return 'creator'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Gumroad'; }
    public function siteUrl(): string { return 'https://.gumroad.com'; }
    protected function requestUrl(string $target): string { return "https://{$target}.gumroad.com/"; }

    public function check(string $target, array $options = []): ScanResult
    {
        if (!preg_match('/^[a-z0-9]{3,20}$/', $target)) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Username must be between 3 and 20 lowercase alphanumeric characters', mode: $this->mode(), key: $this->key());
        }
        return parent::check($target, $options);
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
