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

        if (isset($options['_scanner_deadline_ns']) &&
            ($options['_scanner_deadline_ns'] - hrtime(true)) / 1e9 <= max(0, (int) config('scanner.proxies.behavior.wait_timeout_seconds', 20)) + 2) {
            return $errorResult('Insufficient job deadline budget for proxy acquisition');
        }

        try {
            $lease = $proxyManager->acquire(max(0, $offset), [], $context);
        } catch (Throwable $exception) {
            return $errorResult(ScannerDiagnostics::exception($exception, 'proxy.acquire', $context));
        }
        if ($lease === null) {
            try {
                $remaining = $proxyManager->moduleCooldownRetryAfter($context);
                if ($remaining > 0) {
                    return self::cooldownResult($errorResult, $remaining);
                }
            } catch (Throwable $exception) {
                return $errorResult(ScannerDiagnostics::exception($exception, 'proxy.acquire', $context));
            }
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
        // Reserve a full proxy wait plus cleanup before the FFI queue deadline.
        if (isset($options['_scanner_deadline_ns']) &&
            ($options['_scanner_deadline_ns'] - hrtime(true)) / 1e9 <= max(0, (int) config('scanner.proxies.behavior.wait_timeout_seconds', 20)) + 2) {
            return self::withAttemptDiagnostics($firstResult, [self::attemptDiagnostics($proxyManager, $lease, $firstResult, 1)]);
        }
        try {
            $retryLease = $proxyManager->tierOf($lease['raw']) === 'primary'
                ? $proxyManager->acquireWithGeography('fallback', $retryCountry, $offset + 1, [$lease['raw']], $context)
                : $proxyManager->acquireWithGeography(null, $retryCountry, $offset + 1, [$lease['raw']], $context);
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

    /** An explicit proxy must obey the same shared deadlines as pool leases. */
    public static function runExplicit(
        ProxyManagerService $proxyManager,
        string $proxy,
        callable $attempt,
        callable $errorResult,
        array $context,
    ): ScanResult {
        try {
            $remaining = $proxyManager->moduleCooldownRemaining($proxy, $context);
            if ($remaining > 0) {
                return self::cooldownResult($errorResult, $remaining);
            }
            try {
                $result = $attempt($proxy);
            } catch (Throwable $exception) {
                $result = $errorResult(ScannerDiagnostics::exception($exception, 'validator.request', $context));
                if ($exception instanceof NativeTransportException) {
                    $result = self::withMetadata($result, ['transport_error' => $exception->diagnostics]);
                }
            }

            return self::recordModuleCooldown($proxyManager, $proxy, $result, $context);
        } catch (Throwable $exception) {
            return $errorResult(ScannerDiagnostics::exception($exception, 'proxy.module_cooldown', $context));
        }
    }

    private static function cooldownResult(callable $errorResult, int $seconds): ScanResult
    {
        return self::withMetadata($errorResult('All requested proxies are cooling down for this module; retry after '.$seconds.'s'), [
            'module_proxy_cooldown' => true, 'retry_after_seconds' => $seconds,
        ]);
    }

    private static function recordModuleCooldown(ProxyManagerService $manager, string $proxy, ScanResult $result, array $context): ScanResult
    {
        if (($result->metadata['module_proxy_cooldown'] ?? false) || isset($result->metadata['throttle_backoff'])) {
            return $result;
        }
        $seconds = self::retryAfterSeconds($result);
        if ($seconds === 0 && self::isSiteBlocked($result)) {
            $seconds = max(1, (int) config('scanner.proxies.behavior.module_cooldown_seconds', 300));
        }
        if ($seconds === 0) {
            return $result;
        }
        $scope = ['mode' => $context['mode'] ?? $result->mode, 'module' => $context['module'] ?? $result->key];
        try {
            $until = $manager->recordModuleCooldown($proxy, $scope, $seconds);
            if ($until > 0) {
                return self::withMetadata($result, ['module_proxy_cooldown_until' => $until]);
            }
        } catch (Throwable $exception) {
            ScannerDiagnostics::exception($exception, 'proxy.module_cooldown', $context);

            // Keep the upstream result and release the lease, but do not issue
            // another request when shared cooldown persistence is unavailable.
            return self::withMetadata($result, ['module_proxy_cooldown_persisted' => false]);
        }

        return $result;
    }

    private static function retryAfterSeconds(ScanResult $result): int
    {
        $value = $result->metadata['retry_after_seconds'] ?? null;

        return is_numeric($value) && is_finite((float) $value) && (float) $value > 0
            ? (int) ceil(min((float) $value, PHP_INT_MAX - now()->getTimestamp() - 60)) : 0;
    }

    private static function isSiteBlocked(ScanResult $result): bool
    {
        if ($result->status !== 'Error' || ($result->metadata['module_proxy_cooldown'] ?? false)
            || isset($result->metadata['throttle_backoff'])) {
            return false;
        }
        $status = (int) ($result->metadata['http_status'] ?? 0);
        $connectStatus = (int) (self::transportErrorDiagnostics($result)['proxy_connect_status'] ?? 0);

        return in_array($status, [403, 429], true) || in_array($connectStatus, [403, 429], true)
            || preg_match('/\b(?:403|429)\b|forbidden|cloudflare|\bwaf\b|captcha|ip block|rate[\s_-]*limit|too many requests|throttl|age-verification/i', $result->reason) === 1;
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
        foreach (['http_status', 'retry_after_seconds', 'upstream_error_code', 'module_proxy_cooldown_until'] as $key) {
            if (isset($result->metadata[$key]) && (is_int($result->metadata[$key]) || is_string($result->metadata[$key]))) {
                $diagnostics[$key] = $result->metadata[$key];
            }
        }
        $transportError = self::transportErrorDiagnostics($result);
        if ($transportError !== []) {
            $diagnostics['transport_error'] = $transportError;
        }

        return $diagnostics;
    }

    /** Safe native error evidence only; never copy raw error buffers or URLs. */
    private static function transportErrorDiagnostics(ScanResult $result): array
    {
        $source = $result->metadata['transport_error'] ?? null;
        if (! is_array($source)) {
            return [];
        }
        $diagnostics = [];
        foreach (['curl_code', 'os_errno', 'proxy_connect_status', 'num_connects'] as $key) {
            $value = $source[$key] ?? null;
            if (is_int($value) && $value >= 0) {
                $diagnostics[$key] = $value;
            }
        }
        foreach (['connect_time_ms', 'lookup_time_ms'] as $key) {
            $value = $source[$key] ?? null;
            if ((is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= 0) {
                $diagnostics[$key] = $value;
            }
        }
        foreach ([
            'route' => ['direct', 'proxy'],
            'failure_phase' => ['proxy_connect', 'target_connect', 'unknown_connect', 'direct_connect', 'name_lookup', 'tls', 'transfer', 'unknown'],
            'detail' => ['connection_refused', 'timed_out', 'proxy_dns_failure', 'host_dns_failure', 'proxy_tunnel_failed', 'proxy_authentication_failed', 'connection_failed', 'tls_handshake_failed', 'other', 'unavailable'],
        ] as $key => $allowed) {
            if (in_array($source[$key] ?? null, $allowed, true)) {
                $diagnostics[$key] = $source[$key];
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

        return self::withMetadata($result, ['attempts' => $attempts]);
    }

    private static function withMetadata(ScanResult $result, array $metadata): ScanResult
    {
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
            metadata: array_merge($result->metadata, $metadata),
            externalLinks: $result->externalLinks,
            error: $result->error,
        );
    }

    private static function executeLease(ProxyManagerService $manager, array $lease, callable $attempt, callable $errorResult, array $context): ScanResult
    {
        $context['proxy_id'] = sha1(preg_replace('~^https?://~', '', preg_replace('~//[^/@]+@~', '//', $lease['raw'])));
        $result = null;
        $attempted = false;
        try {
            try {
                $remaining = $manager->moduleCooldownRemaining($lease['raw'], $context);
                if ($remaining > 0) {
                    $result = self::cooldownResult($errorResult, $remaining);
                } else {
                    $attempted = true;
                    $result = $attempt($lease['proxy']);
                }
            } catch (Throwable $exception) {
                $result = $errorResult(ScannerDiagnostics::exception($exception, 'validator.request', $context));
                if ($exception instanceof NativeTransportException) {
                    $result = self::withMetadata($result, ['transport_error' => $exception->diagnostics]);
                }
            }
            if (! $attempted) {
                return $result;
            }
            $result = self::recordModuleCooldown($manager, $lease['raw'], $result, $context);
            // Health is best effort. It must not discard a valid result or stop
            // cleanup, even when the cache is unavailable after the HTTP call.
            try {
                if (self::affectsProxyHealth($result)) {
                    $manager->reportFailure($lease['raw']);
                } elseif (! self::isTransportFailure($result) && ! self::isSiteBlocked($result)
                    && ! ($result->metadata['module_proxy_cooldown'] ?? false)
                    && ! isset($result->metadata['throttle_backoff'])) {
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
        $transportError = self::transportErrorDiagnostics($result);
        if (($transportError['route'] ?? null) === 'proxy') {
            if (($transportError['proxy_connect_status'] ?? null) === 407
                || in_array($transportError['curl_code'] ?? null, [5, 97], true)) {
                return true;
            }
            // Code 7 alone can describe a failed upstream tunnel. Cool the
            // endpoint only when native timings prove its connection failed.
            if (($transportError['curl_code'] ?? null) === 7
                && ($transportError['failure_phase'] ?? null) === 'proxy_connect'
                && ($transportError['proxy_connect_status'] ?? null) === 0
                && array_key_exists('connect_time_ms', $transportError)
                && (float) $transportError['connect_time_ms'] === 0.0) {
                return true;
            }
        }
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

    private static function isTransportFailure(ScanResult $result): bool
    {
        if ($result->status !== 'Error') {
            return false;
        }
        if ((self::transportErrorDiagnostics($result)['curl_code'] ?? 0) > 0) {
            return true;
        }

        return preg_match('/libcurl(?:-impersonate)? request failed \([1-9][0-9]*\)|curl error [1-9][0-9]*\b|curl: \([1-9][0-9]*\)|could not (?:connect|resolve)|failed to connect|connection (?:failed|refused|reset)|timeout|timed out|ssl_read|unexpected eof/i', $result->reason) === 1;
    }

    private static function isRetryableProxyFailure(ScanResult $result): bool
    {
        if ($result->status !== 'Error' || ($result->metadata['module_proxy_cooldown'] ?? false)
            || ($result->metadata['module_proxy_cooldown_persisted'] ?? true) === false) {
            return false;
        }

        // Do not rotate immediately against an explicit upstream cooldown.
        // Return its error/diagnostics without tying up a worker in a sleep;
        // this policy does not reschedule jobs or promise a later retry.
        if (self::retryAfterSeconds($result) > 0) {
            return false;
        }

        $curlCode = self::transportErrorDiagnostics($result)['curl_code'] ?? null;
        if ($curlCode === 7 || ($curlCode === 28 && (bool) config('scanner.proxies.behavior.retry_timeout_failures', false))) {
            return true;
        }

        $reason = strtolower(trim($result->reason));
        if ($reason === '') {
            return false;
        }
        if (preg_match('/libcurl(?:-impersonate)? request failed \(7\)|curl error 7\b|curl: \(7\)/i', $reason) === 1) {
            return true;
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
            // Regional age walls are exit-geography blocks (e.g. RedTube/Tube8
            // serve them on non-U.S. routes); one bounded retry on the
            // U.S.-preferred email retry route can recover the verdict.
            'age-verification',
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
