<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use App\Jobs\RunValidatorJob;
use App\Services\Scanner\ProxyManagerService;
use App\Services\Scanner\QueuedScanService;
use App\Services\Scanner\ScannerEngineService;
use App\Support\ScanRunStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class ProxyPoolIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('scanner.proxies.credentials.username', 'proxy-user');
        config()->set('scanner.proxies.credentials.password', 'p@ss=word');
        config()->set('scanner.proxies.behavior.max_concurrent_per_proxy', 1);
        config()->set('scanner.proxies.behavior.max_retry_per_module', 1);
        config()->set('scanner.proxies.behavior.failure_threshold', 2);
        config()->set('scanner.proxies.behavior.cooldown_min_seconds', 30);
        config()->set('scanner.proxies.behavior.cooldown_max_seconds', 30);
        config()->set('scanner.proxies.behavior.wait_timeout_seconds', 0);
        config()->set('scanner.proxies.behavior.wait_retry_seconds', 1);
    }

    public function test_structured_proxy_pool_populates_sanitized_default_proxy_list(): void
    {
        $this->assertSame([
            'disp.oxylabs.io:8008',
            'disp.oxylabs.io:8005',
            'disp.oxylabs.io:8004',
            'disp.oxylabs.io:8003',
            'disp.oxylabs.io:8002',
            'disp.oxylabs.io:8001',
            'disp.oxylabs.io:8010',
            'disp.oxylabs.io:8009',
            'disp.oxylabs.io:8007',
            'disp.oxylabs.io:8006',
        ], config('scanner.proxy_list'));
    }

    public function test_proxy_manager_resolves_configured_pool_members_with_credentials(): void
    {
        $manager = $this->freshProxyManager();
        $manager->loadFromText('disp.oxylabs.io:8008');

        $this->assertSame(
            'http://proxy-user:p%40ss%3Dword@disp.oxylabs.io:8008',
            $manager->pick(0),
        );
        $this->assertSame(['disp.oxylabs.io:8008'], $manager->all());
    }

    public function test_proxy_manager_cools_down_a_proxy_after_repeated_failures(): void
    {
        $manager = $this->freshProxyManager();
        $manager->loadFromText("disp.oxylabs.io:8008\ndisp.oxylabs.io:8005");

        $first = $manager->acquire(0);
        $this->assertNotNull($first);
        $this->assertSame('disp.oxylabs.io:8008', $first['raw']);
        $manager->reportFailure($first['raw']);
        $manager->release($first['raw']);

        $second = $manager->acquire(0);
        $this->assertNotNull($second);
        $this->assertSame('disp.oxylabs.io:8008', $second['raw']);
        $manager->reportFailure($second['raw']);
        $manager->release($second['raw']);

        $third = $manager->acquire(0);
        $this->assertNotNull($third);
        $this->assertSame('disp.oxylabs.io:8005', $third['raw']);
        $manager->release($third['raw']);
    }

    public function test_validator_job_retries_once_with_a_fallback_proxy_on_proxy_shaped_errors(): void
    {
        ProxyRetryValidator::$seenProxies = [];
        $this->swapValidators(new ProxyRetryValidator());

        $store = app(ScanRunStore::class);
        $runId = $store->createRun(
            mode: 'username',
            targets: ['hienyimba'],
            options: [
                'use_proxy' => true,
                'proxy_list' => "disp.oxylabs.io:8008\ndisp.oxylabs.io:8007",
            ],
            expandedTargets: ['hienyimba'],
            validatorCount: 1,
            expectedResults: 1,
            selectedValidatorKeys: ['proxy-retry'],
        );

        $job = new RunValidatorJob(
            runId: $runId,
            mode: 'username',
            validatorKey: 'proxy-retry',
            validatorMeta: [
                'category' => 'social',
                'site_name' => 'Proxy Retry',
                'url' => 'https://proxy-retry.test',
            ],
            target: 'hienyimba',
            targetIndex: 0,
            validatorIndex: 0,
            options: [
                'use_proxy' => true,
                'proxy_list' => "disp.oxylabs.io:8008\ndisp.oxylabs.io:8007",
            ],
        );

        $job->handle(
            app(ScannerEngineService::class),
            $this->freshProxyManager(),
            $store,
        );

        $results = $store->filteredResults($runId);
        $this->assertCount(1, $results);
        $this->assertSame('Found', $results[0]['status']);
        $this->assertCount(2, ProxyRetryValidator::$seenProxies);
        $this->assertStringContainsString('@disp.oxylabs.io:8008', ProxyRetryValidator::$seenProxies[0]);
        $this->assertStringContainsString('@disp.oxylabs.io:8007', ProxyRetryValidator::$seenProxies[1]);
    }

    public function test_sync_engine_retries_once_with_a_fallback_proxy_on_proxy_shaped_errors(): void
    {
        ProxyRetryValidator::$seenProxies = [];
        $this->swapValidators(new ProxyRetryValidator());

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'proxy-retry',
            target: 'hienyimba',
            options: [
                'use_proxy' => true,
                'proxy_list' => "disp.oxylabs.io:8008\ndisp.oxylabs.io:8007",
            ],
        );

        $this->assertSame('Found', $result->status);
        $this->assertCount(2, ProxyRetryValidator::$seenProxies);
        $this->assertStringContainsString('@disp.oxylabs.io:8008', ProxyRetryValidator::$seenProxies[0]);
        $this->assertStringContainsString('@disp.oxylabs.io:8007', ProxyRetryValidator::$seenProxies[1]);
    }

    public function test_api_tester_page_shows_sanitized_proxy_list_without_credentials(): void
    {
        $response = $this->get('/api-tester');

        $response->assertOk();
        $response->assertSee('disp.oxylabs.io:8008');
        $response->assertDontSee('proxy-user');
        $response->assertDontSee('p@ss=word');
    }

    public function test_prepare_options_can_disable_default_proxy_pool_for_direct_diagnostics(): void
    {
        $service = app(QueuedScanService::class);

        $prepared = $service->prepareOptions([
            'disable_proxy' => true,
            'proxy' => 'disp.oxylabs.io:8008',
            'use_proxy' => true,
        ]);

        $this->assertTrue($prepared['disable_proxy']);
        $this->assertFalse($prepared['use_proxy']);
        $this->assertSame('', $prepared['proxy_list']);
        $this->assertNull($prepared['proxy']);
    }

    private function freshProxyManager(): ProxyManagerService
    {
        $this->app->forgetInstance(ProxyManagerService::class);

        return app(ProxyManagerService::class);
    }

    private function swapValidators(ValidatorContract ...$validators): void
    {
        $this->app->instance('scanner.validators', $validators);
        $this->app->forgetInstance(ScannerEngineService::class);
        $this->app->forgetInstance(QueuedScanService::class);
    }
}

final class ProxyRetryValidator implements ValidatorContract
{
    /** @var array<int, string> */
    public static array $seenProxies = [];

    public function key(): string
    {
        return 'proxy-retry';
    }

    public function category(): string
    {
        return 'social';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Proxy Retry';
    }

    public function siteUrl(): string
    {
        return 'https://proxy-retry.test';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        self::$seenProxies[] = (string) ($options['proxy'] ?? '');

        if (str_contains((string) ($options['proxy'] ?? ''), ':8008')) {
            return ScanResult::fromArray([
                'target' => $target,
                'category' => $this->category(),
                'site_name' => $this->siteName(),
                'url' => $this->siteUrl(),
                'status' => 'Error',
                'reason' => 'Unexpected response body',
                'mode' => $this->mode(),
                'key' => $this->key(),
            ]);
        }

        return ScanResult::fromArray([
            'target' => $target,
            'category' => $this->category(),
            'site_name' => $this->siteName(),
            'url' => $this->siteUrl(),
            'status' => 'Found',
            'reason' => '',
            'mode' => $this->mode(),
            'key' => $this->key(),
        ]);
    }
}
