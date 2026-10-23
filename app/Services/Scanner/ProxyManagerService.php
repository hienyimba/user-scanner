<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class ProxyManagerService
{
    private const STATE_LOCK_KEY = 'scanner:proxy-manager:lock';

    private int $index = 0;

    /** @var array<int, string> */
    private array $proxies = [];

    /**
     * @param string $rawList Multiline proxy list.
     */
    public function loadFromText(string $rawList): void
    {
        $lines = preg_split('/\R/', $rawList) ?: [];
        $this->proxies = Collection::make($lines)
            ->map(static fn (string $line): string => trim($line))
            ->filter(static fn (string $line): bool => $line !== '' && !str_starts_with($line, '#'))
            ->values()
            ->all();

        $this->index = 0;
    }

    public function next(): ?string
    {
        if ($this->proxies === []) {
            return null;
        }

        $proxy = $this->resolveProxyUrl($this->proxies[$this->index]);
        $this->index = ($this->index + 1) % count($this->proxies);

        return $proxy;
    }

    public function pick(int $offset): ?string
    {
        if ($this->proxies === []) {
            return null;
        }

        $index = $offset % count($this->proxies);

        return $this->resolveProxyUrl($this->proxies[$index]);
    }

    public function resolve(string $rawProxy): string
    {
        return $this->resolveProxyUrl($rawProxy);
    }

    public function count(): int
    {
        return count($this->proxies);
    }

    public function validateWorking(int $timeoutSeconds = 5): int
    {
        $working = [];
        foreach ($this->proxies as $proxy) {
            try {
                $response = Http::timeout($timeoutSeconds)
                    ->withOptions([
                        'proxy' => $this->resolveProxyUrl($proxy),
                        'verify' => false,
                    ])
                    ->get('https://www.google.com');

                if ($response->successful()) {
                    $working[] = $proxy;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $this->proxies = array_values($working);
        $this->index = 0;

        return count($this->proxies);
    }

    /** @return array<int, string> */
    public function all(): array
    {
        return $this->proxies;
    }

    /**
     * @param array<int, string> $excludeRaw
     * @return array{raw:string,proxy:string}|null
     */
    public function acquire(int $offset = 0, array $excludeRaw = []): ?array
    {
        return $this->acquireWithTierPreference(null, $offset, $excludeRaw);
    }

    /**
     * @param array<int, string> $excludeRaw
     */
    public function acquirePreferred(string $preferredTier, int $offset = 0, array $excludeRaw = []): ?array
    {
        return $this->acquireWithTierPreference($preferredTier, $offset, $excludeRaw);
    }

    public function tierOf(string $rawProxy): string
    {
        return (string) (($this->configuredPoolEntry($rawProxy)['tier'] ?? 'primary'));
    }

    /**
     * @param array<int, string> $excludeRaw
     */
    private function acquireWithTierPreference(?string $preferredTier, int $offset, array $excludeRaw): ?array
    {
        if ($this->proxies === []) {
            return null;
        }

        $waitTimeoutSeconds = max(0, (int) config('scanner.proxies.behavior.wait_timeout_seconds', 15));
        $waitRetrySeconds = max(1, (int) config('scanner.proxies.behavior.wait_retry_seconds', 2));
        $deadline = microtime(true) + $waitTimeoutSeconds;

        do {
            $lease = $this->withStateLock(fn (): ?array => $this->attemptAcquire($offset, $excludeRaw, $preferredTier));
            if ($lease !== null) {
                return $lease;
            }

            if (microtime(true) >= $deadline) {
                return null;
            }

            usleep($waitRetrySeconds * 1_000_000);
        } while (true);
    }

    public function release(string $rawProxy): void
    {
        $this->withStateLock(function () use ($rawProxy): void {
            $key = $this->proxyFingerprint($rawProxy);
            $count = (int) Cache::get($this->activeCountCacheKey($key), 0);
            if ($count <= 1) {
                Cache::forget($this->activeCountCacheKey($key));
                return;
            }

            Cache::put($this->activeCountCacheKey($key), $count - 1, now()->addMinutes(10));
        });
    }

    public function reportSuccess(string $rawProxy): void
    {
        $this->withStateLock(function () use ($rawProxy): void {
            $key = $this->proxyFingerprint($rawProxy);
            Cache::forget($this->failureCountCacheKey($key));
        });
    }

    public function reportFailure(string $rawProxy): void
    {
        $this->withStateLock(function () use ($rawProxy): void {
            $key = $this->proxyFingerprint($rawProxy);
            $threshold = max(1, (int) config('scanner.proxies.behavior.failure_threshold', 2));
            $failures = (int) Cache::get($this->failureCountCacheKey($key), 0) + 1;

            if ($failures >= $threshold) {
                $cooldownMin = max(1, (int) config('scanner.proxies.behavior.cooldown_min_seconds', 30));
                $cooldownMax = max($cooldownMin, (int) config('scanner.proxies.behavior.cooldown_max_seconds', 90));
                $cooldownSeconds = random_int($cooldownMin, $cooldownMax);

                Cache::put($this->cooldownCacheKey($key), now()->getTimestamp() + $cooldownSeconds, now()->addSeconds($cooldownSeconds + 60));
                Cache::forget($this->failureCountCacheKey($key));

                return;
            }

            Cache::put($this->failureCountCacheKey($key), $failures, now()->addMinutes(10));
        });
    }

    /**
     * @param array<int, string> $excludeRaw
     * @return array{raw:string,proxy:string}|null
     */
    private function attemptAcquire(int $offset, array $excludeRaw, ?string $preferredTier = null): ?array
    {
        $maxConcurrentPerProxy = max(1, (int) config('scanner.proxies.behavior.max_concurrent_per_proxy', 2));
        $excluded = array_fill_keys(array_map([$this, 'proxyFingerprint'], $excludeRaw), true);
        $grouped = [
            'primary' => $this->rotateCandidates($this->filterCandidatesByTier('primary', $excluded), $offset),
            'fallback' => $this->rotateCandidates($this->filterCandidatesByTier('fallback', $excluded), $offset),
        ];

        $tierOrder = match ($preferredTier) {
            'fallback' => ['fallback', 'primary'],
            'primary' => ['primary', 'fallback'],
            default => ['primary', 'fallback'],
        };

        foreach ($tierOrder as $tier) {
            foreach ($grouped[$tier] as $candidate) {
                $fingerprint = $candidate['fingerprint'];
                if ($this->isCoolingDown($fingerprint)) {
                    continue;
                }

                $activeCount = (int) Cache::get($this->activeCountCacheKey($fingerprint), 0);
                if ($activeCount >= $maxConcurrentPerProxy) {
                    continue;
                }

                Cache::put($this->activeCountCacheKey($fingerprint), $activeCount + 1, now()->addMinutes(10));

                return [
                    'raw' => $candidate['raw'],
                    'proxy' => $candidate['resolved'],
                ];
            }
        }

        return null;
    }

    /**
     * @param array<string, bool> $excluded
     * @return array<int, array{raw:string,resolved:string,fingerprint:string}>
     */
    private function filterCandidatesByTier(string $tier, array $excluded): array
    {
        $candidates = [];
        foreach ($this->proxies as $rawProxy) {
            $fingerprint = $this->proxyFingerprint($rawProxy);
            if (isset($excluded[$fingerprint])) {
                continue;
            }

            $proxyConfig = $this->configuredPoolEntry($rawProxy);
            $candidateTier = (string) ($proxyConfig['tier'] ?? 'primary');
            if ($candidateTier !== $tier) {
                continue;
            }

            $candidates[] = [
                'raw' => $rawProxy,
                'resolved' => $this->resolveProxyUrl($rawProxy),
                'fingerprint' => $fingerprint,
            ];
        }

        return $candidates;
    }

    /**
     * @param array<int, array{raw:string,resolved:string,fingerprint:string}> $candidates
     * @return array<int, array{raw:string,resolved:string,fingerprint:string}>
     */
    private function rotateCandidates(array $candidates, int $offset): array
    {
        $count = count($candidates);
        if ($count < 2) {
            return $candidates;
        }

        $index = $offset % $count;

        return [
            ...array_slice($candidates, $index),
            ...array_slice($candidates, 0, $index),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function configuredPoolEntry(string $rawProxy): ?array
    {
        $normalized = $this->normalizeProxyString($rawProxy);
        $parts = parse_url($normalized);
        if ($parts === false) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = (int) ($parts['port'] ?? 0);
        foreach ((array) config('scanner.proxies.pool', []) as $proxy) {
            if (strtolower((string) ($proxy['entry_point'] ?? '')) === $host && (int) ($proxy['port'] ?? 0) === $port) {
                return $proxy;
            }
        }

        return null;
    }

    private function resolveProxyUrl(string $rawProxy): string
    {
        $normalized = $this->normalizeProxyString($rawProxy);
        $parts = parse_url($normalized);
        if ($parts === false) {
            return $normalized;
        }

        if (!empty($parts['user']) || !empty($parts['pass'])) {
            return $normalized;
        }

        $configured = $this->configuredPoolEntry($rawProxy);
        $username = (string) config('scanner.proxies.credentials.username', '');
        $password = (string) config('scanner.proxies.credentials.password', '');
        if ($configured === null || $username === '' || $password === '') {
            return $normalized;
        }

        $scheme = (string) ($parts['scheme'] ?? 'http');
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return sprintf(
            '%s://%s:%s@%s%s',
            $scheme,
            rawurlencode($username),
            rawurlencode($password),
            $host,
            $port,
        );
    }

    private function normalizeProxyString(string $proxy): string
    {
        return preg_match('/^[a-z0-9]+:\/\//i', $proxy) ? $proxy : 'http://' . $proxy;
    }

    private function isCoolingDown(string $fingerprint): bool
    {
        $cooldownUntil = (int) Cache::get($this->cooldownCacheKey($fingerprint), 0);
        if ($cooldownUntil <= now()->getTimestamp()) {
            if ($cooldownUntil !== 0) {
                Cache::forget($this->cooldownCacheKey($fingerprint));
            }

            return false;
        }

        return true;
    }

    private function proxyFingerprint(string $rawProxy): string
    {
        $normalized = $this->normalizeProxyString($rawProxy);
        $parts = parse_url($normalized);
        if ($parts === false) {
            return sha1(strtolower(trim($rawProxy)));
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = (int) ($parts['port'] ?? 0);

        return sha1($host . ':' . $port);
    }

    private function activeCountCacheKey(string $fingerprint): string
    {
        return 'scanner:proxy:active:' . $fingerprint;
    }

    private function failureCountCacheKey(string $fingerprint): string
    {
        return 'scanner:proxy:failures:' . $fingerprint;
    }

    private function cooldownCacheKey(string $fingerprint): string
    {
        return 'scanner:proxy:cooldown:' . $fingerprint;
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    private function withStateLock(callable $callback): mixed
    {
        return Cache::lock(self::STATE_LOCK_KEY, 10)->block(5, $callback);
    }
}
