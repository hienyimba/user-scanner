<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use App\Jobs\RunValidatorJob;
use App\Services\Scanner\ModuleSkipService;
use App\Services\Scanner\QueuedScanService;
use App\Services\Scanner\ScannerEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ModuleSkipFlagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->swapValidators(
            new ModuleSkipFakeValidator('alpha', 'social', 'username', 'Alpha', 'https://alpha.test'),
            new ModuleSkipFakeValidator('beta', 'social', 'username', 'Beta', 'https://beta.test'),
        );
    }

    public function test_ops_dashboard_can_set_and_clear_module_skip_flags(): void
    {
        $setResponse = $this->post(route('ops.module-skips.update'), [
            'mode' => 'username',
            'module_key' => 'alpha',
            'action' => 'set',
            'duration' => '6h',
            'window' => '30d',
        ]);

        $setResponse->assertRedirect(route('ops.metrics', ['window' => '30d']));
        $this->assertDatabaseHas('scanner_module_skip_flags', [
            'mode' => 'username',
            'module_key' => 'alpha',
            'duration' => '6h',
        ]);

        $clearResponse = $this->post(route('ops.module-skips.update'), [
            'mode' => 'username',
            'module_key' => 'alpha',
            'action' => 'clear',
            'window' => '30d',
        ]);

        $clearResponse->assertRedirect(route('ops.metrics', ['window' => '30d']));
        $this->assertDatabaseMissing('scanner_module_skip_flags', [
            'mode' => 'username',
            'module_key' => 'alpha',
        ]);
    }

    public function test_skipped_modules_are_excluded_from_new_queue_plans(): void
    {
        Queue::fake();
        app(ModuleSkipService::class)->setSkip('username', 'alpha', 'permanent');

        $response = $this->postJson('/api/scanner/runs', [
            'mode' => 'username',
            'category' => 'social',
            'targets' => ['alice', 'bob'],
            'stop' => 100,
        ]);

        $response->assertAccepted()->assertJson(['ok' => true]);

        Queue::assertPushed(RunValidatorJob::class, 2);

        $run = app(\App\Support\ScanRunStore::class)->getRun((string) $response->json('run_id'));
        $this->assertNotNull($run);
        $this->assertSame(2, $run['expected_results']);
        $this->assertSame(['beta'], $run['selected_validator_keys']);
    }

    public function test_skipped_module_short_circuits_validator_execution_if_job_starts_after_flag(): void
    {
        app(ModuleSkipService::class)->setSkip('username', 'alpha', '6h');

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'alpha',
            target: 'alice',
            options: [],
        );

        $this->assertSame('Skipped', $result->status);
        $this->assertSame('Skipped from ops dashboard for 6 hours', $result->reason);
    }

    public function test_modules_endpoint_exposes_active_skip_state(): void
    {
        app(ModuleSkipService::class)->setSkip('username', 'alpha', 'permanent');

        $response = $this->getJson('/api/v1/scan/modules/username');

        $response->assertOk();
        $modules = collect($response->json('modules'))->keyBy('key');

        $this->assertTrue((bool) $modules['alpha']['skip_active']);
        $this->assertSame('permanent', $modules['alpha']['skip_duration']);
        $this->assertSame('Skipped from ops dashboard until manually cleared', $modules['alpha']['skip_reason']);
        $this->assertFalse((bool) $modules['beta']['skip_active']);
    }

    private function swapValidators(ValidatorContract ...$validators): void
    {
        $this->app->instance('scanner.validators', $validators);
        $this->app->forgetInstance(ScannerEngineService::class);
        $this->app->forgetInstance(QueuedScanService::class);
    }
}

final class ModuleSkipFakeValidator implements ValidatorContract
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
