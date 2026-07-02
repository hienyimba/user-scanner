<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class NaturalnewsValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'naturalnews';
    }

    public function category(): string
    {
        return 'political';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Naturalnews';
    }

    public function siteUrl(): string
    {
        return 'https://naturalnews.com/author';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://naturalnews.com/author/{$target}/";
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
        return [
            // No connector-specific headers inferred.
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = strtolower($response->body());

        if (in_array($status, [401, 403, 429], true)) {
            return ['Error', $this->key() . ': blocked/rate-limited (HTTP ' . $status . ')'];
        }

        foreach (['captcha', 'challenge', 'verify you are human', 'cloudflare', 'bot check'] as $needle) {
            if ($needle !== '' && str_contains($body, $needle)) {
                return ['Error', $this->key() . ': anti-bot challenge detected'];
            }
        }

        $availableStatuses = [];
        $takenStatuses = [];
        $availableIndicators = ['the page you are looking for cannot be found'];
        $takenIndicators = ['<div class="videoposts">'];

        if ($this->mode() === 'username') {
            if (in_array($status, $availableStatuses, true)) {
                return ['Available', ''];
            }
            if (in_array($status, $takenStatuses, true)) {
                return ['Taken', ''];
            }
            foreach ($takenIndicators as $needle) {
                if ($needle !== '' && str_contains($body, $needle)) {
                    return ['Taken', ''];
                }
            }
            foreach ($availableIndicators as $needle) {
                if ($needle !== '' && str_contains($body, $needle)) {
                    return ['Available', ''];
                }
            }

            return ['Error', $this->key() . ': indeterminate username response (HTTP ' . $status . ')'];
        }

        if (in_array($status, $takenStatuses, true)) {
            return ['Registered', ''];
        }
        if (in_array($status, $availableStatuses, true)) {
            return ['Not Registered', ''];
        }
        foreach ($takenIndicators as $needle) {
            if ($needle !== '' && str_contains($body, $needle)) {
                return ['Registered', ''];
            }
        }
        foreach ($availableIndicators as $needle) {
            if ($needle !== '' && str_contains($body, $needle)) {
                return ['Not Registered', ''];
            }
        }

        return ['Error', $this->key() . ': indeterminate email response (HTTP ' . $status . ')'];
    }
}
