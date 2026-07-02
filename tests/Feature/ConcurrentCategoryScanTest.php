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
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ConcurrentCategoryScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->swapValidators(
            new FakeValidator('alpha', 'social', 'username', 'Alpha', 'https://alpha.test'),
            new FakeValidator('beta', 'social', 'username', 'Beta', 'https://beta.test'),
        );
    }

    public function test_api_batch_run_dispatches_one_job_per_target_validator_pair(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/scanner/runs', [
            'mode' => 'username',
            'category' => 'social',
            'targets' => ['alice', 'bob'],
            'stop' => 100,
        ]);

        $response->assertAccepted()->assertJson(['ok' => true]);

        Queue::assertPushed(RunValidatorJob::class, 4);

        $run = app(ScanRunStore::class)->getRun((string) $response->json('run_id'));
        $this->assertNotNull($run);
        $this->assertSame(4, $run['expected_results']);
        $this->assertSame(2, $run['validator_count']);
        $this->assertSame(2, $run['target_count']);
    }

    public function test_web_category_scan_redirects_to_run_dashboard(): void
    {
        Queue::fake();

        $response = $this->post('/scanner', [
            'mode' => 'username',
            'target' => 'alice',
            'category' => 'social',
            'stop' => 100,
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/scanner?', $location);
        $this->assertStringContainsString('run_id=', $location);

        Queue::assertPushed(RunValidatorJob::class, 2);
    }

    public function test_store_filters_visible_results_when_only_found_is_enabled(): void
    {
        $store = app(ScanRunStore::class);
        $runId = $store->createRun(
            mode: 'username',
            targets: ['alice'],
            options: ['only_found' => true],
            expandedTargets: ['alice'],
            validatorCount: 2,
            expectedResults: 2,
            selectedValidatorKeys: ['alpha', 'beta'],
        );

        $store->markJobStarted($runId);
        $store->appendResult($runId, [
            'target' => 'alice',
            'category' => 'social',
            'site_name' => 'Alpha',
            'url' => 'https://alpha.test',
            'status' => 'Found',
            'reason' => '',
            'extra' => '',
            'mode' => 'username',
            'key' => 'alpha',
        ], 0, 0);

        $store->markJobStarted($runId);
        $store->appendResult($runId, [
            'target' => 'alice',
            'category' => 'social',
            'site_name' => 'Beta',
            'url' => 'https://beta.test',
            'status' => 'Not Found',
            'reason' => '',
            'extra' => '',
            'mode' => 'username',
            'key' => 'beta',
        ], 0, 1);

        $visible = $store->filteredResults($runId);
        $run = $store->getRun($runId);

        $this->assertCount(1, $visible);
        $this->assertSame('alpha', $visible[0]['key']);
        $this->assertSame(2, $run['processed']);
        $this->assertSame('completed', $run['status']);
    }

    public function test_validator_job_records_error_result_without_failing_whole_run(): void
    {
        $this->swapValidators(
            new ThrowingValidator('boom', 'social', 'username', 'Boom', 'https://boom.test')
        );

        $store = app(ScanRunStore::class);
        $runId = $store->createRun(
            mode: 'username',
            targets: ['alice'],
            options: [],
            expandedTargets: ['alice'],
            validatorCount: 1,
            expectedResults: 1,
            selectedValidatorKeys: ['boom'],
        );

        $job = new RunValidatorJob(
            runId: $runId,
            mode: 'username',
            validatorKey: 'boom',
            validatorMeta: [
                'category' => 'social',
                'site_name' => 'Boom',
                'url' => 'https://boom.test',
            ],
            target: 'alice',
            targetIndex: 0,
            validatorIndex: 0,
            options: [],
        );

        $job->handle(
            app(ScannerEngineService::class),
            app(ProxyManagerService::class),
            $store,
        );

        $results = $store->filteredResults($runId);
        $run = $store->getRun($runId);

        $this->assertCount(1, $results);
        $this->assertSame('Error', $results[0]['status']);
        $this->assertSame('completed', $run['status']);
    }

    public function test_failed_job_marks_run_complete_even_if_it_never_appended_normally(): void
    {
        $store = app(ScanRunStore::class);
        $runId = $store->createRun(
            mode: 'username',
            targets: ['alice'],
            options: [],
            expandedTargets: ['alice'],
            validatorCount: 1,
            expectedResults: 1,
            selectedValidatorKeys: ['boom'],
        );

        $job = new RunValidatorJob(
            runId: $runId,
            mode: 'username',
            validatorKey: 'boom',
            validatorMeta: [
                'category' => 'social',
                'site_name' => 'Boom',
                'url' => 'https://boom.test',
            ],
            target: 'alice',
            targetIndex: 0,
            validatorIndex: 0,
            options: [],
        );

        $job->failed(new \RuntimeException('sqlite lock'));

        $results = $store->filteredResults($runId);
        $run = $store->getRun($runId);

        $this->assertCount(1, $results);
        $this->assertSame('Error', $results[0]['status']);
        $this->assertSame(0, $run['queued_jobs']);
        $this->assertSame('completed', $run['status']);
    }

    public function test_auto_skip_config_skips_validator_before_network_execution(): void
    {
        config()->set('scanner.auto_skip.username', ['alpha' => 'Temporarily auto-skipped']);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'alpha',
            target: 'alice',
            options: [],
        );

        $this->assertSame('Skipped', $result->status);
        $this->assertSame('Temporarily auto-skipped', $result->reason);

        config()->set('scanner.auto_skip.username', []);
    }

    private function swapValidators(ValidatorContract ...$validators): void
    {
        $this->app->instance('scanner.validators', $validators);
        $this->app->forgetInstance(ScannerEngineService::class);
        $this->app->forgetInstance(QueuedScanService::class);
    }
}

final class FakeValidator implements ValidatorContract
{
    public function __construct(
        private readonly string $key,
        private readonly string $category,
        private readonly string $mode,
        private readonly string $siteName,
        private readonly string $siteUrl,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function siteName(): string
    {
        return $this->siteName;
    }

    public function siteUrl(): string
    {
        return $this->siteUrl;
    }

    public function check(string $target, array $options = []): ScanResult
    {
        return ScanResult::fromArray([
            'target' => $target,
            'category' => $this->category,
            'site_name' => $this->siteName,
            'url' => $this->siteUrl,
            'status' => 'Taken',
            'mode' => $this->mode,
            'key' => $this->key,
        ]);
    }
}

final class ThrowingValidator implements ValidatorContract
{
    public function __construct(
        private readonly string $key,
        private readonly string $category,
        private readonly string $mode,
        private readonly string $siteName,
        private readonly string $siteUrl,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function siteName(): string
    {
        return $this->siteName;
    }

    public function siteUrl(): string
    {
        return $this->siteUrl;
    }

    public function check(string $target, array $options = []): ScanResult
    {
        throw new \RuntimeException('validator exploded');
    }
}
