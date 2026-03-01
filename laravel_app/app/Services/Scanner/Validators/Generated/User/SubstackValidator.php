<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;

final class SubstackValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'substack';
    }

    public function category(): string
    {
        return 'creator';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Substack';
    }

    public function siteUrl(): string
    {
        return 'https://substack.com';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://{$target}.substack.com";
    }


    protected function requestHeaders(): array
    {
        $isPost = strtoupper($this->requestMethod()) !== 'GET';
        $probeUrl = strtolower($this->requestUrl('probe'));
        $jsonLike = str_contains($probeUrl, '/api/')
            || str_contains($probeUrl, 'graphql')
            || str_contains($probeUrl, 'googleapis.com')
            || str_contains($probeUrl, 'cognito-idp')
            || str_contains($probeUrl, '/rpc/')
            || str_contains($probeUrl, 'application/json');

        return array_filter([
            'Accept' => 'application/json,text/html;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Content-Type' => $isPost ? ($jsonLike ? 'application/json' : 'application/x-www-form-urlencoded; charset=UTF-8') : null,
            'Origin' => parse_url($this->siteUrl(), PHP_URL_SCHEME) . '://' . parse_url($this->siteUrl(), PHP_URL_HOST),
            'Referer' => rtrim($this->siteUrl(), '/') . '/',
            'X-Requested-With' => $isPost ? 'XMLHttpRequest' : null,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    protected function requestQuery(string $target): array
    {
        if (strtoupper($this->requestMethod()) !== 'GET') {
            return [];
        }

        $url = $this->requestUrl($target);
        if (str_contains($url, $target)) {
            return [];
        }

        return [
            'username' => $target,
            'q' => $target,
        ];
    }

    protected function requestBody(string $target): array
    {
        if (strtoupper($this->requestMethod()) === 'GET') {
            return [];
        }

        return [
            'username' => $target,
            'query' => $target,
        ];
    }

    protected function parseConnectorResponse(\Illuminate\Http\Client\Response $response, string $target): array
    {
        $status = $response->status();
        $body = strtolower($response->body());

        $blockedStatuses = [401, 403, 429];
        foreach ($blockedStatuses as $blocked) {
            if ($status === $blocked) {
                return ['Error', 'substack: blocked/rate-limited (HTTP ' . $status . ')'];
            }
        }

        foreach (['captcha', 'challenge', 'verify you are human', 'cloudflare', 'bot check'] as $needle) {
            if (str_contains($body, $needle)) {
                return ['Error', 'substack: anti-bot challenge detected'];
            }
        }

        foreach (['csrf', 'authenticity_token', 'x-csrf-token', 'token required', 'invalid token'] as $needle) {
            if (str_contains($body, $needle) && $status >= 400) {
                return ['Error', 'substack: token/bootstrap extraction failure'];
            }
        }

        $availableStatuses = [404];
        $takenStatuses = [200];
        $availableIndicators = ['available', 'not found'];
        $takenIndicators = ['taken', 'already exists', 'not available'];

        if ($this->mode() === 'username') {
            if (in_array($status, $availableStatuses, true)) {
                return ['Available', ''];
            }
            if (in_array($status, $takenStatuses, true)) {
                return ['Taken', ''];
            }
            foreach ($takenIndicators as $needle) {
                if ($needle !== '' && str_contains($body, strtolower((string) $needle))) {
                    return ['Taken', ''];
                }
            }
            foreach ($availableIndicators as $needle) {
                if ($needle !== '' && str_contains($body, strtolower((string) $needle))) {
                    return ['Available', ''];
                }
            }

            return ['Error', 'substack: indeterminate username response (HTTP ' . $status . ')'];
        }

        if (in_array($status, $takenStatuses, true)) {
            return ['Registered', ''];
        }
        if (in_array($status, $availableStatuses, true)) {
            return ['Not Registered', ''];
        }
        foreach ($takenIndicators as $needle) {
            if ($needle !== '' && str_contains($body, strtolower((string) $needle))) {
                return ['Registered', ''];
            }
        }
        foreach ($availableIndicators as $needle) {
            if ($needle !== '' && str_contains($body, strtolower((string) $needle))) {
                return ['Not Registered', ''];
            }
        }

        return ['Error', 'substack: indeterminate email response (HTTP ' . $status . ')'];
    }

}
