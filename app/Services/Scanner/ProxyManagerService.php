<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class ProxyManagerService
{
    // Drain counter-based workers before deploying this owner-tagged protocol.
    private const LEASE_PREFIX = 'scanner:proxy:v2:lease:';

    private int $index = 0;

    /** @var array<int, string> */
    private array $proxies = [];

    /** @var array<string, array<string, array{lock:Lock,expires:int}>> */
    private array $ownedLeases = [];

    private array $candidates = [];

    /**
     * @param  string  $rawList  Multiline proxy list.
     */
    public function loadFromText(string $rawList): void
    {
        $lines = preg_split('/\R/', $rawList) ?: [];
        $this->proxies = Collection::make($lines)
            ->map(static fn (string $line): string => trim($line))
            ->filter(static fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#'))
            ->values()
            ->all();

        $this->index = 0;
        $this->candidates = [];
        foreach ($this->proxies as $raw) {
            $fingerprint = $this->proxyFingerprint($raw);
            $this->candidates[$fingerprint] = [
                'raw' => $raw, 'fingerprint' => $fingerprint,
                'resolved' => $this->resolveProxyUrl($raw), 'tier' => $this->tierOf($raw),
                'country' => $this->countryCodeOf($raw),
            ];
        }
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

        $this->loadFromText(implode(PHP_EOL, $working));

        return count($this->proxies);
    }

    /** @return array<int, string> */
    public function all(): array
    {
        return $this->proxies;
    }

    /**
     * @param  array<int, string>  $excludeRaw
     * @return array{raw:string,proxy:string,token:string,expires:int}|null
     */
    public function acquire(int $offset = 0, array $excludeRaw = [], array $scope = []): ?array
    {
        return $this->acquireWithTierPreference(null, $offset, $excludeRaw, $scope);
    }

    /**
     * @param  array<int, string>  $excludeRaw
     * @return array{raw:string,proxy:string,token:string,expires:int}|null
     */
    public function acquirePreferred(string $preferredTier, int $offset = 0, array $excludeRaw = [], array $scope = []): ?array
    {
        return $this->acquireWithTierPreference($preferredTier, $offset, $excludeRaw, $scope);
    }

    public function tierOf(string $rawProxy): string
    {
        return (string) (($this->configuredPoolEntry($rawProxy)['tier'] ?? 'primary'));
    }

    /**
     * Configured ISO country code for a pool member, when known.
     */
    public function countryCodeOf(string $rawProxy): ?string
    {
        $country = $this->configuredPoolEntry($rawProxy)['country_code'] ?? null;

        return is_string($country) && $country !== '' ? strtoupper($country) : null;
    }

    /**
     * Acquire a lease preferring a tier and, deliberately, an exit geography.
     * Country preference outranks tier for retries against region-sensitive
     * targets: a matching-country primary beats a non-matching fallback.
     *
     * @param  array<int, string>  $excludeRaw
     * @return array{raw:string,proxy:string,token:string,expires:int}|null
     */
    public function acquireWithGeography(?string $preferredTier, ?string $preferredCountry, int $offset = 0, array $excludeRaw = [], array $scope = []): ?array
    {
        return $this->acquireWithPreference($preferredTier, $preferredCountry, $offset, $excludeRaw, $scope);
    }

    /**
     * @param  array<int, string>  $excludeRaw
     */
    private function acquireWithTierPreference(?string $preferredTier, int $offset, array $excludeRaw, array $scope): ?array
    {
        return $this->acquireWithPreference($preferredTier, null, $offset, $excludeRaw, $scope);
    }

    /**
     * @param  array<int, string>  $excludeRaw
     */
    private function acquireWithPreference(?string $preferredTier, ?string $preferredCountry, int $offset, array $excludeRaw, array $scope): ?array
    {
        if ($this->proxies === []) {
            return null;
        }

        $waitTimeoutSeconds = max(0, (int) config('scanner.proxies.behavior.wait_timeout_seconds', 15));
        $waitRetrySeconds = max(1, (int) config('scanner.proxies.behavior.wait_retry_seconds', 2));
        $deadline = microtime(true) + $waitTimeoutSeconds;

        do {
            $lease = $this->attemptAcquire($offset, $excludeRaw, $preferredTier, $preferredCountry, $scope);
            if ($lease !== null) {
                return $lease;
            }

            // A service's advertised cooldown can last hours. Do not occupy a
            // worker waiting for it, or silently send this check without proxy.
            if ($this->moduleCooldownRetryAfter($scope, $excludeRaw) > 0) {
                return null;
            }

            if (microtime(true) >= $deadline) {
                return null;
            }

            usleep((int) (min($waitRetrySeconds, max(0, $deadline - microtime(true))) * 1_000_000));
        } while (true);
    }

    public function release(string $rawProxy, ?string $token = null): void
    {
        $key = $this->proxyFingerprint($rawProxy);
        $token ??= array_key_first($this->ownedLeases[$key] ?? []);
        $owned = $token !== null ? ($this->ownedLeases[$key][$token] ?? null) : null;
        if ($owned === null) {
            return;
        }
        // Late release after expiry cannot delete a replacement owner's lease.
        $owned['lock']->release();
        unset($this->ownedLeases[$key][$token]);
        if ($this->ownedLeases[$key] === []) {
            unset($this->ownedLeases[$key]);
        }
    }

    public function reportSuccess(string $rawProxy): void
    {
        $this->withHealthLock($rawProxy, function () use ($rawProxy): void {
            $key = $this->proxyFingerprint($rawProxy);
            Cache::forget($this->failureCountCacheKey($key));
        });
    }

    /** Persist a site/exit deadline independently of endpoint health and runs. */
    public function recordModuleCooldown(string $rawProxy, array $scope, int $seconds): int
    {
        $key = $this->moduleCooldownCacheKey($this->proxyFingerprint($rawProxy), $scope);
        if ($key === null || $seconds <= 0) {
            return 0;
        }

        // Serialize max-expiry updates for this pair only. A shorter response
        // arriving later must never overwrite a longer Retry-After deadline.
        return Cache::lock($key.':lock', 5)->block(2, function () use ($key, $seconds): int {
            $now = now()->getTimestamp();
            $until = max((int) Cache::get($key, 0), $now + $seconds);
            if (! Cache::put($key, $until, now()->addSeconds($until - $now + 60))) {
                throw new \RuntimeException('Could not persist module/proxy cooldown');
            }

            return $until;
        });
    }

    public function moduleCooldownRemaining(string $rawProxy, array $scope): int
    {
        $key = $this->moduleCooldownCacheKey($this->proxyFingerprint($rawProxy), $scope);

        return $key === null ? 0 : max(0, (int) Cache::get($key, 0) - now()->getTimestamp());
    }

    /** Earliest eligibility when every non-excluded endpoint is cooling. */
    public function moduleCooldownRetryAfter(array $scope, array $excludeRaw = []): int
    {
        $excluded = array_fill_keys(array_map([$this, 'proxyFingerprint'], $excludeRaw), true);
        $keys = [];
        foreach ($this->candidates as $fingerprint => $candidate) {
            if (! isset($excluded[$fingerprint])) {
                $key = $this->moduleCooldownCacheKey($fingerprint, $scope);
                if ($key === null) {
                    return 0;
                }
                $keys[] = $key;
            }
        }
        if ($keys === []) {
            return 0;
        }
        $deadlines = Cache::many($keys);
        $now = now()->getTimestamp();
        $earliest = PHP_INT_MAX;
        foreach ($keys as $key) {
            $until = (int) ($deadlines[$key] ?? 0);
            if ($until <= $now) {
                return 0;
            }
            $earliest = min($earliest, $until);
        }

        return $earliest - $now;
    }

    public function reportFailure(string $rawProxy): void
    {
        $this->withHealthLock($rawProxy, function () use ($rawProxy): void {
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
     * @param  array<int, string>  $excludeRaw
     * @return array{raw:string,proxy:string,token:string,expires:int}|null
     */
    private function attemptAcquire(int $offset, array $excludeRaw, ?string $preferredTier, ?string $preferredCountry, array $scope): ?array
    {
        $preferredCountry = $preferredCountry !== null ? strtoupper($preferredCountry) : null;
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

        $ordered = [];
        foreach ($tierOrder as $tier) {
            $ordered = array_merge($ordered, $grouped[$tier]);
        }
        if ($preferredCountry !== null) {
            // Deliberate geography: matching-country exits first, tier order
            // preserved within each partition (usort is stable).
            usort($ordered, static fn (array $a, array $b): int => (($b['country'] ?? null) === $preferredCountry ? 1 : 0) <=> (($a['country'] ?? null) === $preferredCountry ? 1 : 0));
        }

        // Batch-read health without a pool-wide mutex. Each slot claim is an
        // independent atomic cache lock, even on the database cache driver.
        $healthKeys = array_map(fn (array $candidate): string => $this->cooldownCacheKey($candidate['fingerprint']), $this->candidates);
        $moduleKeys = array_filter(array_map(fn (array $candidate): ?string => $this->moduleCooldownCacheKey($candidate['fingerprint'], $scope), $this->candidates));
        $cooldowns = $healthKeys !== [] ? Cache::many([...array_values($healthKeys), ...array_values($moduleKeys)]) : [];
        $ttl = max(30, (int) config('scanner.async.job_timeout', 60) + 30,
            (int) config('scanner.proxies.behavior.lease_seconds', 90));
        $now = now()->getTimestamp();
        foreach ($this->ownedLeases as $fingerprint => $leases) {
            foreach ($leases as $token => $owned) {
                if ($owned['expires'] <= $now) {
                    unset($this->ownedLeases[$fingerprint][$token]);
                }
            }
            if (($this->ownedLeases[$fingerprint] ?? []) === []) {
                unset($this->ownedLeases[$fingerprint]);
            }
        }

        foreach ($ordered as $candidate) {
            $fingerprint = $candidate['fingerprint'];
            if ((int) ($cooldowns[$this->cooldownCacheKey($fingerprint)] ?? 0) > $now) {
                continue;
            }
            $moduleKey = $this->moduleCooldownCacheKey($fingerprint, $scope);
            if ($moduleKey !== null && (int) ($cooldowns[$moduleKey] ?? 0) > $now) {
                continue;
            }

            for ($slot = 0; $slot < $maxConcurrentPerProxy; $slot++) {
                $lock = Cache::lock(self::LEASE_PREFIX.$fingerprint.':'.$slot, $ttl);
                if (! $lock->get()) {
                    continue;
                }
                // A previous owner may publish a cooldown and release between
                // our batched read and slot claim. Recheck while owning the slot.
                try {
                    if ($moduleKey !== null && (int) Cache::get($moduleKey, 0) > now()->getTimestamp()) {
                        $lock->release();

                        continue;
                    }
                } catch (\Throwable $error) {
                    $lock->release();
                    throw $error;
                }
                $token = $lock->owner();
                $expires = now()->getTimestamp() + $ttl;
                $this->ownedLeases[$fingerprint][$token] = ['lock' => $lock, 'expires' => $expires];

                return ['raw' => $candidate['raw'], 'proxy' => $candidate['resolved'],
                    'token' => $token, 'expires' => $expires];
            }
        }

        return null;
    }

    /**
     * @param  array<string, bool>  $excluded
     * @return array<int, array{raw:string,resolved:string,fingerprint:string}>
     */
    private function filterCandidatesByTier(string $tier, array $excluded): array
    {
        $candidates = [];
        foreach ($this->candidates as $fingerprint => $candidate) {
            if (isset($excluded[$fingerprint])) {
                continue;
            }

            $candidateTier = $candidate['tier'];
            if ($candidateTier !== $tier) {
                continue;
            }

            $candidates[] = $candidate;
        }

        return $candidates;
    }

    /**
     * @param  array<int, array{raw:string,resolved:string,fingerprint:string}>  $candidates
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

        if (! empty($parts['user']) || ! empty($parts['pass'])) {
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
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

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
        return preg_match('/^[a-z0-9]+:\/\//i', $proxy) ? $proxy : 'http://'.$proxy;
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

        return sha1($host.':'.$port);
    }

    private function failureCountCacheKey(string $fingerprint): string
    {
        return 'scanner:proxy:failures:'.$fingerprint;
    }

    private function cooldownCacheKey(string $fingerprint): string
    {
        return 'scanner:proxy:cooldown:'.$fingerprint;
    }

    private function moduleCooldownCacheKey(string $fingerprint, array $scope): ?string
    {
        $mode = strtolower(trim((string) ($scope['mode'] ?? '')));
        $module = strtolower(trim((string) ($scope['module'] ?? '')));
        if (! in_array($mode, ['username', 'email'], true) || $module === '') {
            return null;
        }

        return 'scanner:proxy:module:v1:cooldown:'.hash('sha256', $mode.':'.$module).':'.$fingerprint;
    }

    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    private function withHealthLock(string $rawProxy, callable $callback): mixed
    {
        $key = $this->proxyFingerprint($rawProxy);
        $lock = Cache::lock('scanner:proxy:v2:health:'.$key, 5);
        if (! $lock->get()) {
            ScannerDiagnostics::event('health_update_skipped', 'proxy.health', ['proxy_id' => $key]);

            return null;
        }
        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
