<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use App\Jobs\RunValidatorJob;
use App\Services\Scanner\QueuedScanService;
use App\Services\Scanner\ScannerEngineService;
use App\Support\ScanRunStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class PublicScanApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->swapValidators(
            new PublicApiFakeValidator('alpha', 'social', 'username', 'Alpha', 'https://alpha.test'),
            new PublicApiFakeValidator('beta', 'social', 'username', 'Beta', 'https://beta.test'),
            new PublicApiFakeValidator('mailbox', 'social', 'email', 'Mailbox', 'https://mailbox.test', 'Registered'),
        );
    }

    public function test_public_scan_create_accepts_minimum_fields(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/scan', [
            'mode' => 'username',
            'target' => 'alice',
        ]);

        $response->assertAccepted()
            ->assertJson([
                'ok' => true,
                'status' => 'queued',
                'mode' => 'username',
                'target' => 'alice',
            ]);

        Queue::assertPushed(RunValidatorJob::class, 2);
    }

    public function test_public_scan_create_maps_show_hits_and_use_proxy(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/scan', [
            'mode' => 'username',
            'target' => 'alice',
            'category' => 'social',
            'use_proxy' => true,
            'show_hits' => true,
        ]);

        $response->assertAccepted()->assertJson([
            'ok' => true,
            'category' => 'social',
        ]);

        $run = app(ScanRunStore::class)->getRun((string) $response->json('run_id'));
        $this->assertTrue((bool) ($run['options']['show_hits'] ?? false));
        $this->assertTrue((bool) ($run['options']['only_found'] ?? false));
        $this->assertTrue((bool) ($run['options']['use_proxy'] ?? false));
    }

    public function test_public_scan_rejects_invalid_payload(): void
    {
        $response = $this->postJson('/api/v1/scan', [
            'mode' => 'bad-mode',
        ]);

        $response->assertStatus(422);
    }

    public function test_public_scan_status_returns_filtered_results_when_show_hits_is_enabled(): void
    {
        $store = app(ScanRunStore::class);
        $runId = $store->createRun(
            mode: 'username',
            targets: ['alice'],
            options: ['show_hits' => true, 'only_found' => true],
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

        $response = $this->getJson("/api/v1/scan/{$runId}");

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'run' => [
                    'id' => $runId,
                    'show_hits' => true,
                    'status' => 'completed',
                ],
            ]);

        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertSame('alpha', $results[0]['key']);
    }

    public function test_public_modules_endpoint_returns_categories_and_modules(): void
    {
        $response = $this->getJson('/api/v1/scan/modules/username');

        $response->assertOk()->assertJson([
            'ok' => true,
            'mode' => 'username',
        ]);

        $this->assertContains('social', $response->json('categories'));
        $this->assertSame('alpha', $response->json('modules.0.key'));
    }

    public function test_public_scan_endpoints_expose_cors_headers(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/scan', [], [], [], [
            'HTTP_ORIGIN' => 'http://external-app.test',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $response->assertNoContent();
        $response->assertHeader('Access-Control-Allow-Origin', '*');
        $this->assertStringContainsString('POST', (string) $response->headers->get('Access-Control-Allow-Methods'));
    }

    public function test_api_tester_page_renders(): void
    {
        $response = $this->get('/api-tester');

        $response->assertOk();
        $response->assertSee('Public API Tester');
        $response->assertSee('POST /api/v1/scan');
        $response->assertSee('June-only modules');
    }

    public function test_external_api_tester_page_renders_remote_base_input(): void
    {
        $response = $this->get('/api-tester/external');

        $response->assertOk();
        $response->assertSee('External API Tester');
        $response->assertSee('API Base URL');
        $response->assertSee('http://userscan.local/api', false);
    }

    private function swapValidators(ValidatorContract ...$validators): void
    {
        $this->app->instance('scanner.validators', $validators);
        $this->app->forgetInstance(ScannerEngineService::class);
        $this->app->forgetInstance(QueuedScanService::class);
    }
}

final class PublicApiFakeValidator implements ValidatorContract
{
    public function __construct(
        private readonly string $key,
        private readonly string $category,
        private readonly string $mode,
        private readonly string $siteName,
        private readonly string $siteUrl,
        private readonly string $status = 'Taken',
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
            'status' => $this->status,
            'mode' => $this->mode,
            'key' => $this->key,
        ]);
    }
}
