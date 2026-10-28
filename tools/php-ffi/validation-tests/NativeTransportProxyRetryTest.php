<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\ScanResult;
use App\Services\Scanner\NativeTransportException;
use App\Services\Scanner\ProxyExecutionPolicy;
use App\Services\Scanner\ProxyManagerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class NativeTransportProxyRetryTest extends TestCase
{
    private const FIRST = 'http://first.invalid:8001';

    private const SECOND = 'http://second.invalid:8002';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::swap(app('cache')->store('array'));
        Cache::flush();
        Http::preventStrayRequests();
        Log::spy();
        config([
            'scanner.proxies.pool' => [],
            'scanner.proxies.credentials.username' => null,
            'scanner.proxies.credentials.password' => null,
            'scanner.proxies.behavior.wait_timeout_seconds' => 0,
            'scanner.proxies.behavior.max_retry_per_module' => 1,
            'scanner.proxies.behavior.retry_timeout_failures' => false,
            'scanner.proxies.behavior.failure_threshold' => 2,
            'scanner.proxies.behavior.max_concurrent_per_proxy' => 1,
            'scanner.proxies.behavior.lease_seconds' => 90,
        ]);
    }

    private function manager(bool $alternate = true): ProxyManagerService
    {
        $manager = new ProxyManagerService;
        $manager->loadFromText(self::FIRST.($alternate ? "\n".self::SECOND : ''));

        return $manager;
    }

    private function scanResult(string $status, string $reason = '', array $metadata = []): ScanResult
    {
        return new ScanResult('offline@example.test', 'other', 'Offline', 'https://offline.test', $status,
            $reason, mode: 'email', metadata: $metadata);
    }

    private function diagnostics(array $overrides = []): array
    {
        return array_replace([
            'curl_code' => 7, 'os_errno' => 111, 'proxy_connect_status' => 0,
            'connect_time_ms' => 0.0, 'lookup_time_ms' => 1.5, 'num_connects' => 0,
            'route' => 'proxy', 'failure_phase' => 'proxy_connect', 'detail' => 'connection_refused',
        ], $overrides);
    }

    public function test_native_connection_error_retries_once_on_a_different_proxy_and_retains_safe_evidence(): void
    {
        $seen = [];
        $diagnostics = $this->diagnostics();
        $result = ProxyExecutionPolicy::run($this->manager(), [], 0,
            function (?string $proxy) use (&$seen, $diagnostics): ScanResult {
                $seen[] = $proxy;

                return count($seen) === 1
                    ? $this->scanResult('Error', 'Native connection failed', ['transport_error' => $diagnostics])
                    : $this->scanResult('Registered');
            }, fn ($reason) => $this->scanResult('Error', $reason));

        $this->assertSame([self::FIRST, self::SECOND], $seen);
        $this->assertSame('Registered', $result->status);
        $this->assertCount(2, $result->metadata['attempts']);
        $this->assertEquals($diagnostics, $result->metadata['attempts'][0]['transport_error']);
        $this->assertArrayNotHasKey('transport_error', $result->metadata['attempts'][1]);
    }

    public function test_legacy_native_and_curl_connection_error_messages_retry_without_direct_fallback(): void
    {
        foreach ([
            'libcurl-impersonate request failed (7): Could not connect to server',
            'libcurl request failed (7): Could not connect to server',
            'cURL error 7: Failed to connect',
            'curl: (7) Failed to connect',
        ] as $reason) {
            $seen = [];
            $result = ProxyExecutionPolicy::run($this->manager(), [], 0,
                function (?string $proxy) use (&$seen, $reason): ScanResult {
                    $seen[] = $proxy;

                    return $this->scanResult('Error', $reason);
                }, fn ($error) => $this->scanResult('Error', $error), fallbackWithoutProxyWhenPoolEmpty: true);
            $this->assertSame([self::FIRST, self::SECOND], $seen, $reason);
            $this->assertSame('Error', $result->status);
        }
    }

    public function test_thrown_native_exception_keeps_diagnostics_during_recovery(): void
    {
        $attempts = 0;
        $result = ProxyExecutionPolicy::run($this->manager(), [], 0,
            function () use (&$attempts): ScanResult {
                if (++$attempts === 1) {
                    throw new NativeTransportException('native connection failed', $this->diagnostics());
                }

                return $this->scanResult('Registered');
            }, fn ($reason) => $this->scanResult('Error', $reason));

        $this->assertSame(2, $attempts);
        $this->assertSame('Registered', $result->status);
        $this->assertSame(7, $result->metadata['attempts'][0]['transport_error']['curl_code']);
        $this->assertSame('connection_refused', $result->metadata['attempts'][0]['transport_error']['detail']);
    }

    public function test_native_connection_error_respects_explicit_retry_after(): void
    {
        $attempts = 0;
        $result = ProxyExecutionPolicy::run($this->manager(), [], 0,
            function () use (&$attempts): ScanResult {
                $attempts++;

                return $this->scanResult('Error', '', ['retry_after_seconds' => 30, 'transport_error' => $this->diagnostics()]);
            }, fn ($reason) => $this->scanResult('Error', $reason));

        $this->assertSame(1, $attempts);
        $this->assertSame(30, $result->metadata['attempts'][0]['retry_after_seconds']);
        $this->assertSame(7, $result->metadata['attempts'][0]['transport_error']['curl_code']);
    }

    public function test_no_alternate_proxy_leaves_the_failure_without_leaking_to_direct(): void
    {
        $seen = [];
        $result = ProxyExecutionPolicy::run($this->manager(alternate: false), [], 0,
            function (?string $proxy) use (&$seen): ScanResult {
                $seen[] = $proxy;

                return $this->scanResult('Error', '', ['transport_error' => $this->diagnostics()]);
            }, fn ($reason) => $this->scanResult('Error', $reason), fallbackWithoutProxyWhenPoolEmpty: true);

        $this->assertSame([self::FIRST], $seen);
        $this->assertSame('Error', $result->status);
        $this->assertCount(1, $result->metadata['attempts']);
    }

    public function test_exhausted_deadline_prevents_connection_attempts(): void
    {
        $attempts = 0;
        $result = ProxyExecutionPolicy::run($this->manager(), ['_scanner_deadline_ns' => hrtime(true) - 1], 0,
            function () use (&$attempts): ScanResult {
                $attempts++;

                return $this->scanResult('Registered');
            }, fn ($reason) => $this->scanResult('Error', $reason));

        $this->assertSame(0, $attempts);
        $this->assertSame('Insufficient job deadline budget for proxy acquisition', $result->reason);
    }

    public function test_connection_retry_reserves_remaining_deadline_budget(): void
    {
        $attempts = 0;
        // Consume the retry's acquisition reserve without a blocking sleep:
        // the callback leaves less than the current wait budget plus cleanup.
        $result = ProxyExecutionPolicy::run($this->manager(), ['_scanner_deadline_ns' => hrtime(true) + 10_000_000_000], 0,
            function () use (&$attempts): ScanResult {
                $attempts++;
                config(['scanner.proxies.behavior.wait_timeout_seconds' => 10]);

                return $this->scanResult('Error', '', ['transport_error' => $this->diagnostics()]);
            }, fn ($reason) => $this->scanResult('Error', $reason));

        $this->assertSame(1, $attempts);
        $this->assertSame('Error', $result->status);
        $this->assertCount(1, $result->metadata['attempts']);
    }

    public function test_transport_failure_cannot_clear_proxy_failures_or_blame_an_upstream_tunnel_failure(): void
    {
        config(['scanner.proxies.behavior.max_retry_per_module' => 0]);
        $fingerprint = sha1('first.invalid:8001');
        foreach ([
            ['proxy_connect_status' => 503, 'connect_time_ms' => 3.0, 'failure_phase' => 'target_connect'],
            ['proxy_connect_status' => null, 'connect_time_ms' => null, 'failure_phase' => 'unknown_connect'],
        ] as $overrides) {
            Cache::put('scanner:proxy:failures:'.$fingerprint, 1, 60);
            $this->runFailure($this->diagnostics($overrides));
            $this->assertSame(1, Cache::get('scanner:proxy:failures:'.$fingerprint));
            $this->assertNull(Cache::get('scanner:proxy:cooldown:'.$fingerprint));
        }
        $this->runFailure($this->diagnostics());
        $this->assertNotNull(Cache::get('scanner:proxy:cooldown:'.$fingerprint));
    }

    public function test_legacy_connection_failure_preserves_health_until_the_fault_is_proven(): void
    {
        config(['scanner.proxies.behavior.max_retry_per_module' => 0]);
        $fingerprint = sha1('first.invalid:8001');
        Cache::put('scanner:proxy:failures:'.$fingerprint, 1, 60);
        ProxyExecutionPolicy::run($this->manager(), [], 0,
            fn () => $this->scanResult('Error', 'libcurl-impersonate request failed (7): Could not connect to server'),
            fn ($reason) => $this->scanResult('Error', $reason));

        $this->assertSame(1, Cache::get('scanner:proxy:failures:'.$fingerprint));
        $this->assertNull(Cache::get('scanner:proxy:cooldown:'.$fingerprint));
    }

    public function test_attempt_diagnostics_discard_raw_and_unrecognized_transport_details(): void
    {
        $sensitive = 'private@example.test';
        $unsafe = $this->diagnostics([
            'detail' => 'failed for '.$sensitive, 'failure_phase' => 'https://secret.invalid',
            'lookup_time_ms' => INF, 'os_errno' => $sensitive,
            'raw_error' => 'https://user:password@proxy.invalid/'.$sensitive,
        ]);
        $attempts = 0;
        $result = ProxyExecutionPolicy::run($this->manager(), [], 0,
            function () use (&$attempts, $unsafe): ScanResult {
                return ++$attempts === 1
                    ? $this->scanResult('Error', 'failed for private@example.test', ['transport_error' => $unsafe])
                    : $this->scanResult('Registered');
            }, fn ($reason) => $this->scanResult('Error', $reason));

        $diagnostics = $result->metadata['attempts'][0]['transport_error'];
        $this->assertSame(7, $diagnostics['curl_code']);
        foreach (['detail', 'failure_phase', 'lookup_time_ms', 'os_errno', 'raw_error'] as $key) {
            $this->assertArrayNotHasKey($key, $diagnostics);
        }
        $this->assertStringNotContainsString($sensitive, json_encode($result->metadata));
        $this->assertStringNotContainsString('password', json_encode($result->metadata));
    }

    public function test_native_timeout_retry_remains_optional(): void
    {
        foreach ([false => 1, true => 2] as $enabled => $expectedAttempts) {
            foreach (['', 'libcurl-impersonate request failed (28): Timeout was reached'] as $reason) {
                config(['scanner.proxies.behavior.retry_timeout_failures' => (bool) $enabled]);
                $attempts = 0;
                ProxyExecutionPolicy::run($this->manager(), [], 0,
                    function () use (&$attempts, $reason): ScanResult {
                        $attempts++;

                        return $this->scanResult('Error', $reason,
                            ['transport_error' => $this->diagnostics(['curl_code' => 28, 'failure_phase' => 'unknown'])]);
                    }, fn ($error) => $this->scanResult('Error', $error));
                $this->assertSame($expectedAttempts, $attempts);
            }
        }
    }

    private function runFailure(array $diagnostics): ScanResult
    {
        return ProxyExecutionPolicy::run($this->manager(), [], 0,
            fn () => $this->scanResult('Error', 'Native connection failed', ['transport_error' => $diagnostics]),
            fn ($reason) => $this->scanResult('Error', $reason));
    }
}
