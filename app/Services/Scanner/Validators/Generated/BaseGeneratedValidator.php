<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class BaseGeneratedValidator implements ValidatorContract
{
    /**
     * @param array<string, mixed> $query
     * @return array{0:string,1:array<string, mixed>}
     */
    protected function normalizeUrlAndQuery(string $url, array $query): array
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['query'])) {
            return [$url, $query];
        }

        $inlineQuery = [];
        foreach (explode('&', $parts['query']) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$rawKey, $rawValue] = array_pad(explode('=', $pair, 2), 2, '');
            $inlineQuery[rawurldecode($rawKey)] = rawurldecode($rawValue);
        }
        $normalizedUrl = preg_replace('/\?.*$/', '', $url) ?? $url;

        return [$normalizedUrl, array_merge($inlineQuery, $query)];
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return $this->siteUrl();
    }

    /** @return array<string,string> */
    protected function requestHeaders(): array
    {
        return [];
    }

    /** @return array<string,string> */
    protected function requestHeadersForTarget(string $target): array
    {
        return $this->requestHeaders();
    }

    /** @return array<string,mixed> */
    protected function requestQuery(string $target): array
    {
        return [];
    }

    /** @return array<string,mixed> */
    protected function requestBody(string $target): array
    {
        return [];
    }

    protected function requestBodyMode(): string
    {
        return 'form';
    }

    protected function requestRawBody(string $target): ?string
    {
        return null;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    /**
     * @return array{0:string,1:string}
     */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }

    protected function makeRequest(string $target, array $options = []): Response
    {
        $request = Http::timeout($this->timeoutSeconds())
            ->withOptions([
                'allow_redirects' => $this->followRedirects(),
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])
            ->withHeaders(array_merge([
            'User-Agent' => config('scanner.user_agent'),
            'Accept' => 'text/html,application/json,*/*;q=0.8',
        ], $this->requestHeadersForTarget($target)));



        if (!empty($options['proxy'])) {
            $request = $request->withOptions(['proxy' => $options['proxy']]);
        }

        $method = strtoupper($this->requestMethod());
        [$url, $query] = $this->normalizeUrlAndQuery($this->requestUrl($target), $this->requestQuery($target));
        $body = $this->requestBody($target);
        $rawBody = $this->requestRawBody($target);
        $headers = array_change_key_case($this->requestHeadersForTarget($target), CASE_LOWER);
        $contentType = (string) ($headers['content-type'] ?? 'application/json');

        if ($query !== [] && $method !== 'GET') {
            $request = $request->withOptions(['query' => $query]);
        }

        if ($method === 'GET') {
            return $request->get($url, $query);
        }

        if ($method === 'POST') {
            if ($rawBody !== null) {
                return $request->withBody($rawBody, $contentType)->post($url);
            }

            if ($body !== []) {
                return match ($this->requestBodyMode()) {
                    'json' => $request->post($url, $body),
                    default => $request->asForm()->post($url, $body),
                };
            }

            return $request->post($url);
        }

        /** @var Response $response */
        $response = $request->send($method, $url, [
            'query' => $query,
            'form_params' => $body,
        ]);

        return $response;
    }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $response = $this->makeRequest($target, $options);
            $challenge = $this->detectBlockedOrChallenged($response);
            [$status, $reason] = $challenge ?? $this->parseConnectorResponse($response, $target);

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), $status, $reason, mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = match (true) {
                str_contains($message, 'timed out') => 'Request timeout',
                str_contains($message, 'ssl_read'),
                str_contains($message, 'unexpected eof while reading') => 'TLS/anti-bot layer closed the connection unexpectedly',
                default => $e->getMessage(),
            };

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }

    protected function looksLikeHtml(Response $response): bool
    {
        $contentType = strtolower((string) $response->header('Content-Type'));
        $body = ltrim($response->body());

        return str_contains($contentType, 'text/html')
            || str_starts_with($body, '<!doctype')
            || str_starts_with($body, '<html');
    }

    /**
     * @return array{0:string,1:string}|null
     */
    protected function detectBlockedOrChallenged(Response $response): ?array
    {
        $status = $response->status();
        if (in_array($status, [401, 403, 429], true)) {
            return ['Error', $this->key() . ': blocked/rate-limited (HTTP ' . $status . ')'];
        }

        if ($status === 404) {
            return null;
        }

        $effectiveUri = strtolower((string) ($response->effectiveUri() ?? ''));
        if ($effectiveUri !== '' && (str_contains($effectiveUri, '/verify-human/') || str_contains($effectiveUri, 'captcha'))) {
            return ['Error', $this->key() . ': anti-bot challenge detected'];
        }

        if (!$this->looksLikeHtml($response)) {
            return null;
        }

        $body = strtolower($response->body());
        foreach ([
            'verify you are human',
            'bot check',
            'security verification',
            'checking your browser',
            'just a moment',
            'access denied',
        ] as $needle) {
            if ($needle !== '' && str_contains($body, $needle)) {
                return ['Error', $this->key() . ': anti-bot challenge detected'];
            }
        }

        return null;
    }
}
