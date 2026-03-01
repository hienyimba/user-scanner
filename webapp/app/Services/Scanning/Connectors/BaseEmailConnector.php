<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors;

use App\Enums\ResultStatus;
use App\Enums\ScanType;
use App\Services\Scanning\Contracts\ConnectorInterface;
use App\Services\Scanning\NormalizedScanResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

abstract class BaseEmailConnector implements ConnectorInterface
{
    abstract protected function endpointUrl(): string;

    abstract protected function siteName(): string;

    abstract public function key(): string;

    abstract public function category(): string;

    abstract protected function probeMethod(): string;

    abstract protected function emailField(): string;

    /**
     * @return array<string, string>
     */
    abstract protected function probeQuery(string $email): array;

    /**
     * @return array<string, string>
     */
    abstract protected function probeBody(string $email): array;

    /**
     * @return list<string>
     */
    abstract protected function registrationIndicators(): array;

    /**
     * @return list<string>
     */
    abstract protected function nonRegistrationIndicators(): array;

    protected function probeEndpointPath(): string
    {
        return '/api/check-email';
    }

    protected function probeUrl(): string
    {
        return rtrim($this->endpointUrl(), '/') . $this->probeEndpointPath();
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json,text/plain,*/*',
            'Origin' => rtrim($this->endpointUrl(), '/'),
            'Referer' => rtrim($this->endpointUrl(), '/') . '/',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Scanner-Connector' => $this->key(),
            'X-Scanner-Category' => $this->category(),
        ];
    }

    /**
     * @return list<int>
     */
    protected function registeredStatusCodes(): array
    {
        return [200, 201, 202, 409, 422];
    }

    /**
     * @return list<int>
     */
    protected function nonRegisteredStatusCodes(): array
    {
        return [404, 204];
    }

    /**
     * @return list<string>
     */
    protected function registrationJsonPaths(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    protected function nonRegistrationJsonPaths(): array
    {
        return [];
    }

    protected function endpointSupported(): bool
    {
        $url = $this->probeUrl();

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    protected function unsupportedReason(): string
    {
        return sprintf('Unsupported or malformed probe endpoint for connector [%s]: %s', $this->key(), $this->probeUrl());
    }

    public function supports(ScanType $type): bool
    {
        return $type === ScanType::Email;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function scan(string $target, array $options = []): NormalizedScanResult
    {
        $attempts = max(1, (int) ($options['retry_limit'] ?? config('scanner.queue.default_retry_limit', 3)));
        $timeout = max(2, (int) ($options['timeout_seconds'] ?? config('scanner.queue.default_timeout_seconds', 20)));
        $url = $this->probeUrl();

        if (! $this->endpointSupported()) {
            return new NormalizedScanResult(
                connectorKey: $this->key(),
                category: $this->category(),
                siteName: $this->siteName(),
                status: ResultStatus::Error,
                reason: $this->unsupportedReason(),
                checkedUrl: $url,
                confidence: 'low',
                metadata: [
                    'target' => $target,
                    'endpoint_supported' => false,
                    'action' => 'Review connector probeUrl/probeEndpointPath configuration',
                ],
            );
        }

        $lastError = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $request = $this->buildRequest($options, $timeout, $attempt);
                $response = $this->dispatchProbe($request, $url, $target);

                return $this->mapResponse($response, $url, $target, $attempt);
            } catch (ConnectionException|RequestException $exception) {
                $lastError = $exception;
            } catch (Throwable $exception) {
                $lastError = $exception;
            }
        }

        return new NormalizedScanResult(
            connectorKey: $this->key(),
            category: $this->category(),
            siteName: $this->siteName(),
            status: ResultStatus::Error,
            reason: sprintf('Request failed after %d attempts: %s', $attempts, $lastError?->getMessage() ?? 'unknown'),
            checkedUrl: $url,
            confidence: 'low',
            metadata: [
                'target' => $target,
                'action' => 'Inspect connector endpoint/proxy and platform availability',
            ],
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function buildRequest(array $options, int $timeout, int $attempt): PendingRequest
    {
        $proxy = $this->resolveProxy($options, $attempt);

        $request = Http::timeout($timeout)
            ->withHeaders($this->requestHeaders())
            ->withHeaders([
                'User-Agent' => $this->resolveUserAgent($options, $attempt),
                'Cache-Control' => 'no-cache',
            ])
            ->withOptions([
                'allow_redirects' => true,
                'verify' => (bool) ($options['verify_tls'] ?? true),
            ]);

        if ($proxy !== null) {
            $request = $request->withOptions(['proxy' => $proxy]);
        }

        return $request;
    }

    protected function dispatchProbe(PendingRequest $request, string $url, string $email): Response
    {
        $method = strtoupper($this->probeMethod());

        if ($method === 'POST') {
            return $request->asJson()->post($url, $this->probeBody($email));
        }

        return $request->get($url, $this->probeQuery($email));
    }

    protected function mapResponse(Response $response, string $url, string $target, int $attempt): NormalizedScanResult
    {
        $statusCode = $response->status();
        $body = mb_strtolower($response->body());

        if ($statusCode === 429) {
            return $this->buildResult(ResultStatus::Error, $url, 'Rate limited by target platform', $statusCode, $target, $attempt, 'low');
        }

        if ($statusCode >= 500) {
            return $this->buildResult(ResultStatus::Error, $url, 'Server-side error on target platform', $statusCode, $target, $attempt, 'low');
        }

        $json = $response->json();
        if (is_array($json)) {
            if ($this->jsonHasAnyPath($json, $this->nonRegistrationJsonPaths())) {
                return $this->buildResult(ResultStatus::NotRegistered, $url, '', $statusCode, $target, $attempt, 'high');
            }
            if ($this->jsonHasAnyPath($json, $this->registrationJsonPaths())) {
                return $this->buildResult(ResultStatus::Registered, $url, '', $statusCode, $target, $attempt, 'high');
            }
        }

        if (in_array($statusCode, $this->nonRegisteredStatusCodes(), true)
            || $this->containsAny($body, $this->nonRegistrationIndicators())) {
            return $this->buildResult(ResultStatus::NotRegistered, $url, '', $statusCode, $target, $attempt, 'mid');
        }

        if (in_array($statusCode, $this->registeredStatusCodes(), true)
            || $this->containsAny($body, $this->registrationIndicators())) {
            return $this->buildResult(ResultStatus::Registered, $url, '', $statusCode, $target, $attempt, 'mid');
        }

        return $this->buildResult(
            ResultStatus::Error,
            $url,
            'Unable to determine email registration from connector response signatures',
            $statusCode,
            $target,
            $attempt,
            'low'
        );
    }

    /**
     * @param list<string> $needles
     */
    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $paths
     */
    protected function jsonHasAnyPath(array $payload, array $paths): bool
    {
        foreach ($paths as $path) {
            $value = $this->jsonPathValue($payload, $path);
            if (is_bool($value)) {
                if ($value) {
                    return true;
                }
                continue;
            }

            if (is_numeric($value)) {
                if ((int) $value > 0) {
                    return true;
                }
                continue;
            }

            if (is_string($value)) {
                $normalized = mb_strtolower(trim($value));
                if ($normalized !== '' && ! in_array($normalized, ['false', '0', 'no', 'null'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function jsonPathValue(array $payload, string $path): mixed
    {
        $cursor = $payload;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function resolveProxy(array $options, int $attempt): string|null
    {
        $single = $options['proxy'] ?? null;
        if (is_string($single) && $single !== '') {
            return $single;
        }

        $proxies = $options['proxies'] ?? null;
        if (! is_array($proxies) || $proxies === []) {
            return null;
        }

        $index = ($attempt - 1) % count($proxies);
        $candidate = $proxies[$index] ?? null;

        return is_string($candidate) && $candidate !== '' ? $candidate : null;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function resolveUserAgent(array $options, int $attempt): string
    {
        $agents = $options['user_agents'] ?? [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
        ];

        if (! is_array($agents) || $agents === []) {
            return 'Mozilla/5.0';
        }

        $agent = $agents[($attempt - 1) % count($agents)] ?? 'Mozilla/5.0';

        return is_string($agent) && $agent !== '' ? $agent : 'Mozilla/5.0';
    }

    protected function buildResult(
        ResultStatus $status,
        string $url,
        string $reason,
        int $statusCode,
        string $target,
        int $attempt,
        string $confidence = 'mid',
    ): NormalizedScanResult {
        return new NormalizedScanResult(
            connectorKey: $this->key(),
            category: $this->category(),
            siteName: $this->siteName(),
            status: $status,
            reason: $reason,
            checkedUrl: $url,
            confidence: $confidence,
            metadata: [
                'http_status' => $statusCode,
                'target' => $target,
                'attempt' => $attempt,
                'probe_method' => strtoupper($this->probeMethod()),
                'email_field' => $this->emailField(),
                'probe_url' => $this->probeUrl(),
            ],
        );
    }
}
