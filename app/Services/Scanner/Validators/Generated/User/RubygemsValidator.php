<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/rubygems.py
// parity-class: generated

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class RubygemsValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'rubygems';
    }

    public function category(): string
    {
        return 'dev';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Rubygems';
    }

    public function siteUrl(): string
    {
        return 'https://rubygems.org/profiles/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://rubygems.org/profiles/{$target}";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function requestHeaders(): array
    {
        return [];
    }

    protected function requestQuery(string $target): array
    {
        return [];
    }


    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 200) {
            return ['Taken', ''];
        }

        if ($status === 404) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected status: ' . $status];
    }

    protected function buildExtraMetadata(Response $response, string $target, string $status): string
    {
        if ($status !== 'Taken') {
            return '';
        }

        $body = $response->body();
        $metadata = [];

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $titleMatch) === 1) {
            $name = trim(str_replace('Profile of', '', explode('|', $titleMatch[1])[0] ?? ''));
            if ($name !== '') {
                $metadata['Name'] = $name;
            }
        }

        if (preg_match('/Gems\s*<span[^>]*>(\d+)<\/span>/i', $body, $gemsMatch) === 1) {
            $metadata['Gems Count'] = $gemsMatch[1];
        }

        return $this->metadataSummary($metadata);
    }
}
