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
            return self::withAttemptDiagnostics($firstResult, [self::attemptDiagnostics($proxyManager, $lease, $firstResult, 1)]);
        }

        // Resolve the default here, not only in the synchronous engine: queued
        // jobs call this policy directly. This remains a country preference,
        // not a hard country filter; username tier selection is unchanged.
        $mode = $context['mode'] ?? $firstResult->mode;
        $retryCountry = array_key_exists('retry_country', $options)
            ? $options['retry_country']
            : ($mode === 'email' ? config('scanner.proxies.behavior.email_retry_country', 'US') : null);
        $retryCountry = is_string($retryCountry) && trim($retryCountry) !== '' ? strtoupper(trim($retryCountry)) : null;
        try {
            $retryLease = $proxyManager->tierOf($lease['raw']) === 'primary'
                ? $proxyManager->acquireWithGeography('fallback', $retryCountry, $offset + 1, [$lease['raw']])
                : $proxyManager->acquireWithGeography(null, $retryCountry, $offset + 1, [$lease['raw']]);
        } catch (Throwable $exception) {
            ScannerDiagnostics::exception($exception, 'proxy.retry_acquire', $context);

            return self::withAttemptDiagnostics($firstResult, [self::attemptDiagnostics($proxyManager, $lease, $firstResult, 1)]);
        }
        if ($retryLease === null) {
            return self::withAttemptDiagnostics($firstResult, [self::attemptDiagnostics($proxyManager, $lease, $firstResult, 1)]);
        }

        $finalResult = self::executeLease($proxyManager, $retryLease, $attempt, $errorResult, $context);

        return self::withAttemptDiagnostics($finalResult, [
            self::attemptDiagnostics($proxyManager, $lease, $firstResult, 1),
            self::attemptDiagnostics($proxyManager, $retryLease, $finalResult, 2),
        ]);
    }

    /**
     * Redacted per-attempt evidence for intermittent production-only failures:
     * HTTP status, Retry-After and safe upstream codes only; never the target,
     * credentials or response bodies.
     *
     * @param  array{raw:string,proxy:string,token:string,expires:int}  $lease
     * @return array<string, mixed>
     */
    private static function attemptDiagnostics(ProxyManagerService $proxyManager, array $lease, ScanResult $result, int $attemptNumber): array
    {
        $diagnostics = [
            'attempt' => $attemptNumber,
            'proxy_id' => sha1(preg_replace('~^https?://~', '', preg_replace('~//[^/@]+@~', '//', $lease['raw']))),
            'route_tier' => $proxyManager->tierOf($lease['raw']),
            'route_country' => $proxyManager->countryCodeOf($lease['raw']),
            'status' => $result->status,
        ];
        foreach (['http_status', 'retry_after_seconds', 'upstream_error_code'] as $key) {
            if (isset($result->metadata[$key]) && (is_int($result->metadata[$key]) || is_string($result->metadata[$key]))) {
                $diagnostics[$key] = $result->metadata[$key];
            }
        }

        return $diagnostics;
    }

    /**
     * @param  array<int, array<string, mixed>>  $attempts
     */
    private static function withAttemptDiagnostics(ScanResult $result, array $attempts): ScanResult
    {
        // Retain recovered failures too. Avoid the extra payload for a normal
        // single-attempt success, whose request metadata is already present.
        if ($attempts === [] || ($result->status !== 'Error' && count($attempts) === 1)) {
            return $result;
        }

        return new ScanResult(
            target: $result->target,
            category: $result->category,
            siteName: $result->siteName,
            url: $result->url,
            status: $result->status,
            reason: $result->reason,
            extra: $result->extra,
            mode: $result->mode,
            key: $result->key,
            platform: $result->platform,
            normalizedStatus: $result->normalizedStatus,
            profileUrl: $result->profileUrl,
            confidence: $result->confidence,
            metadata: array_merge($result->metadata, ['attempts' => $attempts]),
            externalLinks: $result->externalLinks,
            error: $result->error,
        );
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

        // Do not rotate immediately against an explicit upstream cooldown.
        // Return its error/diagnostics without tying up a worker in a sleep;
        // this policy does not reschedule jobs or promise a later retry.
        if (is_numeric($result->metadata['retry_after_seconds'] ?? null)
            && (float) $result->metadata['retry_after_seconds'] > 0) {
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
