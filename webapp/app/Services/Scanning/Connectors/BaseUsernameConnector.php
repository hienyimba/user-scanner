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

abstract class BaseUsernameConnector implements ConnectorInterface
{
    abstract protected function profileUrl(string $username): string;

    abstract protected function siteName(): string;

    abstract public function key(): string;

    abstract public function category(): string;

    public function supports(ScanType $type): bool
    {
        return $type === ScanType::Username;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function scan(string $target, array $options = []): NormalizedScanResult
    {
        $url = $this->profileUrl($target);
        $attempts = max(1, (int) ($options['retry_limit'] ?? config('scanner.queue.default_retry_limit', 3)));
        $timeout = max(2, (int) ($options['timeout_seconds'] ?? config('scanner.queue.default_timeout_seconds', 20)));

        $lastError = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $request = $this->buildRequest($options, $timeout, $attempt);
                $response = $request->get($url);

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
            metadata: ['target' => $target],
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function buildRequest(array $options, int $timeout, int $attempt): PendingRequest
    {
        $proxy = $this->resolveProxy($options, $attempt);

        $request = Http::acceptJson()
            ->timeout($timeout)
            ->withHeaders([
                'User-Agent' => $this->resolveUserAgent($options, $attempt),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
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

        if ($statusCode === 404 || $this->containsAny($body, $this->availableIndicators())) {
            return $this->buildResult(ResultStatus::Available, $url, '', $statusCode, $target, $attempt);
        }

        if ($response->ok() || $this->containsAny($body, $this->takenIndicators())) {
            return $this->buildResult(ResultStatus::Taken, $url, '', $statusCode, $target, $attempt);
        }

        return $this->buildResult(ResultStatus::Error, $url, 'Unexpected status response', $statusCode, $target, $attempt, 'mid');
    }

    /**
     * @return list<string>
     */
    protected function takenIndicators(): array
    {
        return ['profile', 'user', '@'];
    }

    /**
     * @return list<string>
     */
    protected function availableIndicators(): array
    {
        return ['not found', 'page does not exist', 'couldn\'t find'];
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
        string $confidence = 'high',
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
            ],
        );
    }
}
