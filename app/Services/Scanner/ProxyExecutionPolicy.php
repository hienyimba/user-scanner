<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use App\DTO\ScanResult;
use Throwable;

final class ProxyExecutionPolicy
{
    /**
     * @param  array<string, mixed>  $options
     * @param  callable(?string): ScanResult  $attempt
     * @param  callable(string): ScanResult  $errorResult
     */
    public static function run(
        ProxyManagerService $proxyManager,
        array $options,
        int $offset,
        callable $attempt,
        callable $errorResult,
        bool $fallbackWithoutProxyWhenPoolEmpty = false,
        array $context = [],
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

        try {
            $lease = $proxyManager->acquire(max(0, $offset));
        } catch (Throwable $exception) {
            return $errorResult(ScannerDiagnostics::exception($exception, 'proxy.acquire', $context));
        }
        if ($lease === null) {
            ScannerDiagnostics::event('proxy_capacity_exhausted', 'proxy.acquire', $context);

            return $errorResult('No proxy capacity available from configured pool');
        }

        $firstResult = self::executeLease($proxyManager, $lease, $attempt, $errorResult, $context);
        if (! self::isRetryableProxyFailure($firstResult) || self::maxProxyRetries() < 1) {
            return $firstResult;
        }

        try {
            $retryLease = $proxyManager->tierOf($lease['raw']) === 'primary'
                ? $proxyManager->acquirePreferred('fallback', $offset + 1, [$lease['raw']])
                : $proxyManager->acquire($offset + 1, [$lease['raw']]);
        } catch (Throwable $exception) {
            ScannerDiagnostics::exception($exception, 'proxy.retry_acquire', $context);

            return $firstResult;
        }
        if ($retryLease === null) {
            return $firstResult;
        }

        return self::executeLease($proxyManager, $retryLease, $attempt, $errorResult, $context);
    }

    private static function executeLease(ProxyManagerService $manager, array $lease, callable $attempt, callable $errorResult, array $context): ScanResult
    {
        $context['proxy_id'] = sha1(preg_replace('~^https?://~', '', preg_replace('~//[^/@]+@~', '//', $lease['raw'])));
        $result = null;
        try {
            try {
                $result = $attempt($lease['proxy']);
            } catch (Throwable $exception) {
                $result = $errorResult(ScannerDiagnostics::exception($exception, 'validator.request', $context));
            }
            // Health is best effort. It must not discard a valid result or stop
            // cleanup, even when the cache is unavailable after the HTTP call.
            try {
                if (self::affectsProxyHealth($result)) {
                    $manager->reportFailure($lease['raw']);
                } else {
                    $manager->reportSuccess($lease['raw']);
                }
            } catch (Throwable $exception) {
                ScannerDiagnostics::exception($exception, 'proxy.health', $context);
            }

            return $result;
        } finally {
            // Retry a transient release failure; otherwise the owner-tagged
            // lease expires. Never force-release a newer worker's lock.
            for ($attemptNumber = 1; $attemptNumber <= 2; $attemptNumber++) {
                try {
                    $manager->release($lease['raw'], $lease['token']);
                    break;
                } catch (Throwable $exception) {
                    ScannerDiagnostics::exception($exception, 'proxy.release', $context + ['attempt' => $attemptNumber]);
                    if ($attemptNumber < 2) {
                        usleep(25_000);
                    }
                }
            }
        }
    }

    private static function maxProxyRetries(): int
    {
        return max(0, (int) config('scanner.proxies.behavior.max_retry_per_module', 1));
    }

    private static function affectsProxyHealth(ScanResult $result): bool
    {
        if ($result->status !== 'Error') {
            return false;
        }
        $reason = strtolower($result->reason);
        // A site's WAF/rate limit does not establish a broken proxy transport.
        // Keep per-module retries, but do not cool that endpoint for all sites.
        foreach (['proxy authentication', 'http 407', 'status code 407', 'could not resolve proxy', 'curl error 5:', 'curl error 97:'] as $needle) {
            if (str_contains($reason, $needle)) {
                return true;
            }
        }

        return (bool) config('scanner.proxies.behavior.retry_timeout_failures', false)
            && (str_contains($reason, 'proxy connect') || str_contains($reason, 'connect tunnel'));
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
            'forbidden',
            'cloudflare',
            'waf',
            'captcha',
            'ip block',
            'rate limited',
            'ssl_read',
            'unexpected eof',
            'unexpected response body',
            'invalid api response format',
        ] as $needle) {
            if (str_contains($reason, $needle)) {
                return true;
            }
        }

        if ((bool) config('scanner.proxies.behavior.retry_timeout_failures', false)) {
            foreach ([
                'timeout',
                'timed out',
                'curl error 28',
                'operation timed out',
            ] as $needle) {
                if (str_contains($reason, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }
}
