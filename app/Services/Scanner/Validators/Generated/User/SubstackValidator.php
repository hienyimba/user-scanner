<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class SubstackValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'substack'; }
    public function category(): string { return 'creator'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Substack'; }
    public function siteUrl(): string { return 'https://.substack.com'; }
    protected function requestUrl(string $target): string { return "https://{$target}.substack.com"; }
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Encoding' => 'identity',
            'accept-language' => 'en-US,en;q=0.9',
            'priority' => 'u=0, i',
        ];
    }
    public function check(string $target, array $options = []): ScanResult
    {
        if (strlen($target) < 4 || strlen($target) > 32) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Length must be 4-32 characters', mode: $this->mode(), key: $this->key());
        }
        if (!preg_match('/^[a-z0-9]+$/', $target)) {
            $reason = preg_match('/[A-Z]/', $target) ? 'Use lowercase letters only' : 'Usernames can only contain lowercase letters and numbers';
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
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
