<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use App\DTO\ScanResult;

final class ProxyExecutionPolicy
{
    /**
     * @param array<string, mixed> $options
     * @param callable(?string): ScanResult $attempt
     * @param callable(string): ScanResult $errorResult
     */
    public static function run(
        ProxyManagerService $proxyManager,
        array $options,
        int $offset,
        callable $attempt,
        callable $errorResult,
        bool $fallbackWithoutProxyWhenPoolEmpty = false,
    ): ScanResult {
        $proxyList = trim((string) ($options['proxy_list'] ?? ''));
        if ($proxyList !== '') {
            $proxyManager->loadFromText($proxyList);
        }

        if ($proxyManager->count() === 0) {
            return $fallbackWithoutProxyWhenPoolEmpty
                ? $attempt(null)
                : $errorResult('No proxy capacity available from configured pool');
        }

        $lease = $proxyManager->acquire(max(0, $offset));
        if ($lease === null) {
            return $errorResult('No proxy capacity available from configured pool');
        }

        $firstResult = $attempt($lease['proxy']);
        if (!self::isRetryableProxyFailure($firstResult) || self::maxProxyRetries() < 1) {
            self::finalizeLease($proxyManager, $lease['raw'], $firstResult);

            return $firstResult;
        }

        $proxyManager->reportFailure($lease['raw']);
        $proxyManager->release($lease['raw']);

        $retryLease = $proxyManager->tierOf($lease['raw']) === 'primary'
            ? $proxyManager->acquirePreferred('fallback', $offset + 1, [$lease['raw']])
            : $proxyManager->acquire($offset + 1, [$lease['raw']]);
        if ($retryLease === null) {
            return $firstResult;
        }

        $retryResult = $attempt($retryLease['proxy']);
        self::finalizeLease($proxyManager, $retryLease['raw'], $retryResult);

        return $retryResult;
    }

    private static function finalizeLease(ProxyManagerService $proxyManager, string $rawProxy, ScanResult $result): void
    {
        if (self::isRetryableProxyFailure($result)) {
            $proxyManager->reportFailure($rawProxy);
        } else {
            $proxyManager->reportSuccess($rawProxy);
        }

        $proxyManager->release($rawProxy);
    }

    private static function maxProxyRetries(): int
    {
        return max(0, (int) config('scanner.proxies.behavior.max_retry_per_module', 1));
    }

    private static function isRetryableProxyFailure(ScanResult $result): bool
    {
        if ($result->status !== 'Error') {
            return false;
        }

        $reason = strtolower(trim($result->reason));
        if ($reason === '') {
            return false;
        }

        foreach ([
            '403',
            '429',
            'timeout',
            'timed out',
            'forbidden',
            'cloudflare',
            'waf',
            'captcha',
            'ip block',
            'rate limited',
            'ssl_read',
            'unexpected eof',
            'curl error',
            'unexpected response body',
            'invalid api response format',
        ] as $needle) {
            if (str_contains($reason, $needle)) {
                return true;
            }
        }

        return false;
    }
}
