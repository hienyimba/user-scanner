<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class MyanimelistValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'myanimelist';
    }

    public function category(): string
    {
        return 'entertainment';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Myanimelist';
    }

    public function siteUrl(): string
    {
        return 'https://myanimelist.net';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://myanimelist.net";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 7;
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
        $availableIndicators = [];
        $takenIndicators = ['already have an account'];

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
