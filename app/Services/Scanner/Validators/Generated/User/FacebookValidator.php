<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class FacebookValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'facebook';
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
        return 'Facebook';
    }

    public function siteUrl(): string
    {
        return 'https://www.facebook.com';
    }

    protected function timeoutSeconds(): int
    {
        return 8;
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.facebook.com/{$target}";
    }

    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
        ];
    }

    public function check(string $target, array $options = []): ScanResult
    {
        if (strlen($target) < 1 || strlen($target) > 50) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Length must be 1-50 characters', mode: $this->mode(), key: $this->key());
        }

        if (!preg_match('/^[a-zA-Z0-9.]+$/', $target)) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Only letters, numbers and periods allowed', mode: $this->mode(), key: $this->key());
        }

        if (ctype_digit($target)) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Username cannot be numbers only', mode: $this->mode(), key: $this->key());
        }

        if (str_starts_with($target, '.') || str_ends_with($target, '.')) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Username cannot start or end with a period', mode: $this->mode(), key: $this->key());
        }

        return parent::check($target, $options);
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();
        $hasProfileMarkers = preg_match('/<meta property="og:title" content="[^"]+"/', $body)
            && preg_match('/<meta property="og:url" content="https:\/\/www\.facebook\.com\/[^\"]+"/', $body);
        $hasUnavailableMarker = str_contains($body, "This content isn't available right now");

        if ($status === 429) {
            return ['Error', 'Rate limited by Facebook'];
        }

        if ($status >= 500) {
            return ['Error', 'Facebook returned HTTP ' . $status];
        }

        if ($hasProfileMarkers && !$hasUnavailableMarker) {
            return ['Taken', ''];
        }

        if ($hasUnavailableMarker && !$hasProfileMarkers) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
