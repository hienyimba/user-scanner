<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use App\Jobs\RunValidatorJob;
use App\Services\Scanner\ProxyExecutionPolicy;
use App\Services\Scanner\ProxyManagerService;
use App\Services\Scanner\ScannerEngineService;
use App\Services\Scanner\Validators\Generated\Email\FirefoxValidator;
use App\Support\ScanRunStore;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class ModuleProxyCooldownTest extends TestCase
{
    use RefreshDatabase;

    private const FIRST = 'http://first.invalid:8001';

    private const SECOND = 'http://second.invalid:8002';

    private const THIRD = 'http://third.invalid:8003';

    private const SCOPE = ['mode' => 'email', 'module' => 'alpha'];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::swap(app('cache')->store('array'));
        Cache::flush();
        Http::preventStrayRequests();
        Log::spy();
        $this->freezeTime();
        config(['scanner.proxies.pool' => [], 'scanner.proxies.credentials.username' => null,
            'scanner.proxies.credentials.password' => null, 'scanner.proxies.behavior.wait_timeout_seconds' => 0,
            'scanner.proxies.behavior.max_concurrent_per_proxy' => 1, 'scanner.proxies.behavior.max_retry_per_module' => 1,
            'scanner.proxies.behavior.module_cooldown_seconds' => 300, 'scanner.proxies.behavior.failure_threshold' => 2]);
    }

    private function manager(?string $pool = null): ProxyManagerService
    {
        $manager = new ProxyManagerService;
        $manager->loadFromText($pool ?? self::FIRST."\n".self::SECOND);

        return $manager;
    }

    private function scanResult(string $status = 'Registered', array $metadata = [], string $reason = ''): ScanResult
    {
        return new ScanResult('synthetic@example.test', 'other', 'Offline', 'https://example.test', $status,
            $reason, mode: 'email', key: 'alpha', metadata: $metadata);
    }

    private function runPolicy(ProxyManagerService $manager, callable $attempt, array $scope = self::SCOPE, array $options = []): ScanResult
    {
        return ProxyExecutionPolicy::run($manager, $options, 0, $attempt,
            fn (string $reason): ScanResult => $this->scanResult('Error', reason: $reason),
            fallbackWithoutProxyWhenPoolEmpty: true, context: $scope);
    }

    public function test_later_run_with_a_fresh_manager_cannot_reuse_a_retry_after_proxy(): void
    {
        $seen = [];
        $first = $this->runPolicy($this->manager(), function ($proxy) use (&$seen): ScanResult {
            $seen[] = $proxy;

            return $this->scanResult('Error', ['http_status' => 429, 'retry_after_seconds' => 300], 'HTTP 429');
        }, self::SCOPE + ['run_id' => 'first-run']);
        $second = $this->runPolicy($this->manager(), function ($proxy) use (&$seen): ScanResult {
            $seen[] = $proxy;

            return $this->scanResult();
        }, self::SCOPE + ['run_id' => 'different-run']);
        $this->assertSame([self::FIRST, self::SECOND], $seen);
        $this->assertSame('Registered', $second->status);
        $this->assertCount(1, $first->metadata['attempts']);
        $this->assertSame(now()->getTimestamp() + 300, $first->metadata['module_proxy_cooldown_until']);
    }

    public function test_another_modules_success_does_not_clear_a_site_cooldown(): void
    {
        $manager = $this->manager(self::FIRST);
        $this->runPolicy($manager, fn () => $this->scanResult('Error', ['http_status' => 429, 'retry_after_seconds' => 300]));
        $seen = [];
        $this->runPolicy($this->manager(self::FIRST), function ($proxy) use (&$seen): ScanResult {
            $seen[] = $proxy;

            return $this->scanResult();
        }, ['mode' => 'email', 'module' => 'beta']);
        // Even an already-running successful check of alpha must not erase it.
        $manager->reportSuccess(self::FIRST);
        $blocked = $this->runPolicy($this->manager(self::FIRST), function (): ScanResult {
            $this->fail('A cooled module/proxy pair reached the request callback');
        });
        $this->assertSame([self::FIRST], $seen);
        $this->assertSame(300, $blocked->metadata['retry_after_seconds']);
        $this->assertTrue($blocked->metadata['module_proxy_cooldown']);
        $this->assertNull(Cache::get('scanner:proxy:cooldown:'.sha1('first.invalid:8001')));
    }

    public function test_long_retry_after_is_not_capped_to_the_default_or_extended_by_shorter_responses(): void
    {
        $manager = $this->manager(self::FIRST);
        $until = $manager->recordModuleCooldown(self::FIRST, self::SCOPE, 172800);
        $this->travel(60)->seconds();
        $this->assertSame($until, $manager->recordModuleCooldown(self::FIRST, self::SCOPE, 10));
        $this->assertSame(172740, $this->manager()->moduleCooldownRemaining(self::FIRST, self::SCOPE));
        $this->travel(172739)->seconds();
        $this->assertNull($manager->acquire(0, [], self::SCOPE));
        $this->travel(1)->seconds();
        $lease = $manager->acquire(0, [], self::SCOPE);
        $this->assertNotNull($lease);
        $manager->release($lease['raw'], $lease['token']);
    }

    #[DataProvider('blockedResponses')]
    public function test_unadvertised_site_blocks_get_a_shared_default_cooldown(array $metadata, string $reason): void
    {
        config(['scanner.proxies.behavior.max_retry_per_module' => 0]);
        $manager = $this->manager(self::FIRST);
        Cache::put('scanner:proxy:failures:'.sha1('first.invalid:8001'), 1, 600);
        $this->runPolicy($manager, fn () => $this->scanResult('Error', $metadata, $reason));
        $this->assertSame(300, $manager->moduleCooldownRemaining(self::FIRST, self::SCOPE));
        $this->assertSame(1, Cache::get('scanner:proxy:failures:'.sha1('first.invalid:8001')));
        $this->assertNull(Cache::get('scanner:proxy:cooldown:'.sha1('first.invalid:8001')));
    }

    public static function blockedResponses(): array
    {
        return [
            '429' => [['http_status' => 429], 'Unexpected response'],
            '403' => [['http_status' => 403], 'Unexpected response'],
            'zero-header' => [['http_status' => 429, 'retry_after_seconds' => 0], 'Rate limited'],
            'legacy-hyphen' => [[], 'Service blocked/rate-limited'],
            'challenge' => [[], 'Cloudflare CAPTCHA challenge'],
            'json-throttle' => [['http_status' => 200], 'Explicit API throttle'],
        ];
    }

    public function test_all_cooled_proxies_return_the_earliest_retry_without_network_wait_or_direct_fallback(): void
    {
        $manager = $this->manager();
        $manager->recordModuleCooldown(self::FIRST, self::SCOPE, 300);
        $manager->recordModuleCooldown(self::SECOND, self::SCOPE, 90);
        config(['scanner.proxies.behavior.wait_timeout_seconds' => 20]);
        $start = microtime(true);
        $result = $this->runPolicy($manager, function (): ScanResult {
            $this->fail('Cooldown exhaustion must not become a direct request');
        });
        $this->assertLessThan(2, microtime(true) - $start);
        $this->assertSame('Error', $result->status);
        $this->assertSame(90, $result->metadata['retry_after_seconds']);
        $this->assertArrayNotHasKey('attempts', $result->metadata);
    }

    public function test_retry_selection_skips_an_already_cooled_fallback_proxy(): void
    {
        config(['scanner.proxies.pool' => [
            ['entry_point' => 'first.invalid', 'port' => 8001, 'tier' => 'primary'],
            ['entry_point' => 'second.invalid', 'port' => 8002, 'tier' => 'fallback'],
            ['entry_point' => 'third.invalid', 'port' => 8003, 'tier' => 'primary'],
        ]]);
        $manager = $this->manager(self::FIRST."\n".self::SECOND."\n".self::THIRD);
        $manager->recordModuleCooldown(self::SECOND, self::SCOPE, 300);
        $seen = [];
        $result = $this->runPolicy($manager, function ($proxy) use (&$seen): ScanResult {
            $seen[] = $proxy;

            return count($seen) === 1 ? $this->scanResult('Error', ['http_status' => 429], 'HTTP 429') : $this->scanResult();
        });
        $this->assertSame([self::FIRST, self::THIRD], $seen);
        $this->assertSame('Registered', $result->status);
        $this->assertSame(300, $manager->moduleCooldownRemaining(self::FIRST, self::SCOPE));
    }

    public function test_both_failed_attempts_remain_cooled_for_the_next_job(): void
    {
        $manager = $this->manager();
        $seen = [];
        $this->runPolicy($manager, function ($proxy) use (&$seen): ScanResult {
            $seen[] = $proxy;

            return $this->scanResult('Error', ['http_status' => 429], 'HTTP 429');
        });
        $this->assertSame([self::FIRST, self::SECOND], $seen);
        $this->runPolicy($this->manager(), function (): ScanResult {
            $this->fail('The next job reused a proxy from an earlier throttled attempt');
        });
    }

    public function test_scope_separates_modes_modules_and_normalizes_endpoint_credentials(): void
    {
        $manager = $this->manager(self::FIRST);
        $manager->recordModuleCooldown('http://one:secret@FIRST.invalid:8001', self::SCOPE, 300);
        $this->assertSame(300, $manager->moduleCooldownRemaining('first.invalid:8001', self::SCOPE));
        $this->assertSame(300, $manager->moduleCooldownRemaining('http://two:changed@first.invalid:8001', self::SCOPE));
        foreach ([['mode' => 'username', 'module' => 'alpha'], ['mode' => 'email', 'module' => 'beta']] as $scope) {
            $lease = $manager->acquire(0, [], $scope);
            $this->assertNotNull($lease);
            $manager->release($lease['raw'], $lease['token']);
        }
        $serialized = json_encode(Cache::getStore()->all(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('secret', $serialized);
        $this->assertStringNotContainsString('first.invalid', $serialized);
    }

    public function test_scope_cooldown_is_rechecked_after_claiming_a_slot_from_a_stale_snapshot(): void
    {
        $manager = $this->manager();
        $racing = new ModuleCooldownRacingCache(Cache::getFacadeRoot(), fn () => $manager->recordModuleCooldown(self::FIRST, self::SCOPE, 300));
        Cache::swap($racing);
        $lease = $manager->acquire(0, [], self::SCOPE);
        $this->assertSame(self::SECOND, $lease['raw']);
        $manager->release($lease['raw'], $lease['token']);
        $otherManager = $this->manager(self::FIRST);
        $other = $otherManager->acquire(0, [], ['mode' => 'email', 'module' => 'beta']);
        $this->assertNotNull($other, 'The stale slot must be released before trying another endpoint');
        $otherManager->release($other['raw'], $other['token']);
    }

    public function test_scope_cooldown_does_not_depend_on_slot_count_and_does_not_erase_global_health(): void
    {
        config(['scanner.proxies.behavior.max_concurrent_per_proxy' => 2]);
        $manager = $this->manager(self::FIRST);
        $inFlight = $manager->acquire(0, [], self::SCOPE);
        $manager->recordModuleCooldown(self::FIRST, self::SCOPE, 300);
        $this->assertNull($this->manager(self::FIRST)->acquire(0, [], self::SCOPE));
        $manager->release($inFlight['raw'], $inFlight['token']);
        $this->assertNull($manager->acquire(0, [], self::SCOPE));
        Cache::put('scanner:proxy:cooldown:'.sha1('first.invalid:8001'), now()->getTimestamp() + 600, 660);
        $this->assertNull($manager->acquire(0, [], ['mode' => 'email', 'module' => 'beta']));
    }

    public function test_a_successful_response_can_advertise_a_retry_after_without_losing_its_verdict(): void
    {
        $manager = $this->manager(self::FIRST);
        $result = $this->runPolicy($manager, fn () => $this->scanResult('Registered', ['http_status' => 200, 'retry_after_seconds' => 45]));
        $this->assertSame('Registered', $result->status);
        $this->assertSame(45, $manager->moduleCooldownRemaining(self::FIRST, self::SCOPE));
        $this->runPolicy($this->manager(self::FIRST), function (): ScanResult {
            $this->fail('A successful verdict erased the advertised cooldown');
        });
    }

    public function test_local_backoff_gates_do_not_poison_an_unused_proxy_or_report_success(): void
    {
        $manager = $this->manager(self::FIRST);
        Cache::put('scanner:proxy:failures:'.sha1('first.invalid:8001'), 1, 600);
        $this->runPolicy($manager, fn () => $this->scanResult('Error',
            ['throttle_backoff' => 'service', 'retry_after_seconds' => 120], 'Explicit throttle backoff active'));
        $this->assertSame(0, $manager->moduleCooldownRemaining(self::FIRST, self::SCOPE));
        $this->assertSame(1, Cache::get('scanner:proxy:failures:'.sha1('first.invalid:8001')));
    }

    public function test_cooldown_write_failure_stops_retries_and_releases_the_lease(): void
    {
        Cache::swap(new ModuleCooldownFaultCache(Cache::getFacadeRoot(), failWrites: true));
        $manager = $this->manager();
        $attempts = 0;
        $result = $this->runPolicy($manager, function () use (&$attempts): ScanResult {
            $attempts++;

            return $this->scanResult('Error', ['http_status' => 429], 'HTTP 429');
        });
        $this->assertSame(1, $attempts);
        $this->assertFalse($result->metadata['module_proxy_cooldown_persisted']);
        $lease = $manager->acquire();
        $this->assertNotNull($lease);
        $manager->release($lease['raw'], $lease['token']);
    }

    public function test_explicit_proxy_cooldown_read_failure_fails_closed(): void
    {
        Cache::swap(new ModuleCooldownFaultCache(Cache::getFacadeRoot(), failReads: true));
        $result = ProxyExecutionPolicy::runExplicit($this->manager(), self::FIRST,
            function (): ScanResult {
                $this->fail('An unavailable cooldown store must not become a request');
            }, fn ($reason) => $this->scanResult('Error', reason: $reason), self::SCOPE);
        $this->assertSame('Error', $result->status);
        $this->assertStringContainsString('cooldown store unavailable', $result->reason);
    }

    public function test_post_acquisition_cache_failure_does_not_clear_health_without_a_request(): void
    {
        Cache::put('scanner:proxy:failures:'.sha1('first.invalid:8001'), 1, 600);
        Cache::swap(new ModuleCooldownFaultCache(Cache::getFacadeRoot(), failReads: true, failOnRead: 2));
        $result = $this->runPolicy($this->manager(self::FIRST), function (): ScanResult {
            $this->fail('The final cooldown check failed, so no request is allowed');
        });
        $this->assertSame('Error', $result->status);
        $this->assertSame(1, Cache::get('scanner:proxy:failures:'.sha1('first.invalid:8001')));
        $lease = $this->manager(self::FIRST)->acquire();
        $this->assertNotNull($lease);
    }

    public function test_retry_after_http_date_from_a_real_validator_is_persisted(): void
    {
        Http::fake(['*' => Http::response(['error' => 'Too many requests'], 429,
            ['Retry-After' => now()->addSeconds(120)->toRfc7231String()])]);
        $scope = ['mode' => 'email', 'module' => 'firefox'];
        $manager = $this->manager(self::FIRST);
        $result = $this->runPolicy($manager, fn ($proxy) => (new FirefoxValidator)->check('synthetic@example.test', ['proxy' => $proxy]), $scope);
        $seconds = $result->metadata['retry_after_seconds'];
        $this->assertGreaterThanOrEqual(118, $seconds);
        $this->assertLessThanOrEqual(120, $seconds);
        $this->assertSame($seconds, $manager->moduleCooldownRemaining(self::FIRST, $scope));
        $this->runPolicy($this->manager(self::FIRST), function (): ScanResult {
            $this->fail('A later check ignored the HTTP-date Retry-After');
        }, $scope);
        Http::assertSentCount(1);
    }

    public function test_explicit_proxy_requests_obey_and_record_shared_cooldowns(): void
    {
        $validator = new ModuleCooldownFixtureValidator;
        $this->app->instance('scanner.validators', [$validator]);
        $this->app->forgetInstance(ScannerEngineService::class);
        $engine = app(ScannerEngineService::class);
        $first = $engine->runPlannedValidator('email', $validator->key(), 'one@example.test',
            ['proxy' => self::FIRST, 'enrich_metadata' => false]);
        $second = $engine->runPlannedValidator('email', $validator->key(), 'two@example.test',
            ['proxy' => self::FIRST, 'enrich_metadata' => false]);
        $this->assertSame('Error', $first->status);
        $this->assertTrue($second->metadata['module_proxy_cooldown']);
        $this->assertSame([self::FIRST], $validator->seen);
    }

    public function test_two_queue_jobs_in_different_runs_skip_the_previously_throttled_exit(): void
    {
        $validator = new ModuleCooldownFixtureValidator;
        $this->app->instance('scanner.validators', [$validator]);
        $this->app->forgetInstance(ScannerEngineService::class);
        $engine = app(ScannerEngineService::class);
        $store = app(ScanRunStore::class);
        foreach (['one@example.test', 'two@example.test'] as $target) {
            $options = ['use_proxy' => true, 'proxy_list' => self::FIRST."\n".self::SECOND, 'enrich_metadata' => false];
            $run = $store->createRun('email', [$target], expectedResults: 1, validatorCount: 1);
            $job = new RunValidatorJob($run, 'email', $validator->key(), [], $target, 0, 0, $options);
            $job->handle($engine, $this->manager(), $store);
        }
        $this->assertSame([self::FIRST, self::SECOND], $validator->seen);
        $this->assertSame(['Error', 'Registered'], DB::table('scan_run_results')->orderBy('id')->pluck('status')->all());
    }

    public function test_independent_php_processes_share_database_cooldowns_and_other_module_success_cannot_clear_them(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'module-cooldown-');
        try {
            $pdo = new PDO('sqlite:'.$file);
            $pdo->exec('CREATE TABLE cache (key VARCHAR(255) PRIMARY KEY, value TEXT NOT NULL, expiration INTEGER NOT NULL)');
            $pdo->exec('CREATE TABLE cache_locks (key VARCHAR(255) PRIMARY KEY, owner VARCHAR(255) NOT NULL, expiration INTEGER NOT NULL)');
            $pdo = null;
            $run = function (string $action) use ($file): array {
                $process = new Process([PHP_BINARY, '-d', 'xdebug.mode=off', __DIR__.'/module-cooldown-worker.php', $file, $action], base_path());
                $process->setTimeout(30)->mustRun();
                $this->assertJson($process->getOutput(), substr($process->getOutput(), 0, 2500));

                return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            };
            $writer = $run('write');
            $other = $run('other-success');
            $reader = $run('read');
            $this->assertCount(3, array_unique([$writer['pid'], $other['pid'], $reader['pid']]));
            $this->assertSame(self::FIRST, $writer['proxy']);
            $this->assertSame(self::FIRST, $other['proxy']);
            $this->assertSame(self::SECOND, $reader['proxy']);
            $this->assertGreaterThan(86000, $reader['remaining']);
        } finally {
            foreach ([$file, $file.'-journal', $file.'-wal', $file.'-shm'] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}

final class ModuleCooldownFixtureValidator implements ValidatorContract
{
    public array $seen = [];

    public function key(): string
    {
        return 'module-cooldown-fixture';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function category(): string
    {
        return 'other';
    }

    public function siteName(): string
    {
        return 'Cooldown Fixture';
    }

    public function siteUrl(): string
    {
        return 'https://example.test';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $this->seen[] = $options['proxy'] ?? null;

        return new ScanResult($target, 'other', $this->siteName(), $this->siteUrl(),
            count($this->seen) === 1 ? 'Error' : 'Registered', mode: 'email', key: $this->key(),
            metadata: count($this->seen) === 1 ? ['http_status' => 429, 'retry_after_seconds' => 300] : ['http_status' => 200]);
    }
}

final class ModuleCooldownRacingCache
{
    private bool $armed = true;

    public function __construct(private Repository $repository, private mixed $publish) {}

    public function __call(string $method, array $arguments): mixed
    {
        $result = $this->repository->{$method}(...$arguments);
        if ($method === 'many' && $this->armed) {
            $this->armed = false;
            ($this->publish)();
        }

        return $result;
    }
}

final class ModuleCooldownFaultCache
{
    private int $reads = 0;

    public function __construct(private Repository $repository, private bool $failWrites = false, private bool $failReads = false, private int $failOnRead = 1) {}

    public function __call(string $method, array $arguments): mixed
    {
        if (isset($arguments[0]) && is_string($arguments[0]) && str_contains($arguments[0], 'scanner:proxy:module:v1:cooldown:')) {
            if ($this->failWrites && $method === 'put') {
                return false;
            }
            if ($method === 'get' && $this->failReads && ++$this->reads === $this->failOnRead) {
                throw new \RuntimeException('Synthetic cooldown store unavailable');
            }
        }

        return $this->repository->{$method}(...$arguments);
    }
}
