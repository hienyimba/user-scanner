<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class TiktokValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'tiktok';
    }

    public function category(): string
    {
        return 'social';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Tiktok';
    }

    public function siteUrl(): string
    {
        return 'https://www.tiktok.com/@';
    }

    protected function timeoutSeconds(): int
    {
        return 4;
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.tiktok.com/@{$target}";
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Encoding' => 'identity',
            'Accept-Language' => 'en-US,en;q=0.9',
            'sec-fetch-dest' => 'document',
            'Connection' => 'keep-alive',
        ];
    }

    public function check(string $target, array $options = []): ScanResult
    {
        if (strlen($target) < 2 || strlen($target) > 24) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Length must be 2-24 characters', mode: $this->mode(), key: $this->key());
        }

        if (ctype_digit($target)) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Usernames cannot contain numbers only', mode: $this->mode(), key: $this->key());
        }

        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $target)) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Usernames can only contain letters, numbers, underscores and periods', mode: $this->mode(), key: $this->key());
        }

        if (str_starts_with($target, '.') || str_ends_with($target, '.')) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Username cannot start nor end with a period', mode: $this->mode(), key: $this->key());
        }

        return parent::check($target, $options);
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() === 200) {
            if (str_contains(strtolower($response->body()), 'statuscode":10221')) {
                return ['Available', ''];
            }

            return ['Taken', ''];
        }

        return ['Error', 'Unable to load tiktok'];
    }
}
