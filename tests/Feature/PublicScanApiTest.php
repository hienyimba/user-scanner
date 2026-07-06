<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use App\Jobs\RunValidatorJob;
use App\Services\Scanner\MetadataCapabilityService;
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
                'reused' => false,
                'cached' => false,
            ]);

        Queue::assertPushed(RunValidatorJob::class, 2);
    }

    public function test_public_scan_create_maps_show_hits_use_proxy_and_store(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/scan', [
            'mode' => 'username',
            'target' => 'alice',
            'category' => 'social',
            'use_proxy' => true,
            'show_hits' => true,
            'store' => true,
        ]);

        $response->assertAccepted()->assertJson([
            'ok' => true,
            'category' => 'social',
        ]);

        $run = app(ScanRunStore::class)->getRun((string) $response->json('run_id'));
        $this->assertTrue((bool) ($run['options']['show_hits'] ?? false));
        $this->assertTrue((bool) ($run['options']['only_found'] ?? false));
        $this->assertTrue((bool) ($run['options']['use_proxy'] ?? false));
        $this->assertTrue((bool) ($run['options']['store'] ?? false));
    }

    public function test_public_scan_create_reuses_running_matching_run_when_store_is_true(): void
    {
        Queue::fake();

        $store = app(ScanRunStore::class);
        $runId = $store->createRun(
            mode: 'username',
            targets: ['alice'],
            options: ['category' => 'social', 'store' => true],
            expandedTargets: ['alice'],
            validatorCount: 1,
            expectedResults: 1,
            selectedValidatorKeys: ['alpha'],
        );
        $store->markJobStarted($runId);

        $response = $this->postJson('/api/v1/scan', [
            'mode' => 'username',
            'target' => 'alice',
            'category' => 'social',
            'store' => true,
        ]);

        $response->assertAccepted()->assertJson([
            'ok' => true,
            'run_id' => $runId,
            'status' => 'running',
            'reused' => true,
            'cached' => false,
        ]);

        Queue::assertNothingPushed();
    }

    public function test_public_scan_create_reuses_recent_completed_matching_run_within_48_hours(): void
    {
        Queue::fake();

        $store = app(ScanRunStore::class);
        $runId = $store->createRun(
            mode: 'username',
            targets: ['alice'],
            options: ['category' => 'social', 'store' => true],
            expandedTargets: ['alice'],
            validatorCount: 1,
            expectedResults: 1,
            selectedValidatorKeys: ['alpha'],
        );
        $store->markJobStarted($runId);
        $store->appendResult($runId, [
            'target' => 'alice',
            'category' => 'social',
            'site_name' => 'Alpha',
            'url' => 'https://alpha.test',
            'status' => 'Found',
            'reason' => '',
            'mode' => 'username',
            'key' => 'alpha',
        ], 0, 0);

        $response = $this->postJson('/api/v1/scan', [
            'mode' => 'username',
            'target' => 'alice',
            'category' => 'social',
            'store' => true,
        ]);

        $response->assertOk()->assertJson([
            'ok' => true,
            'run_id' => $runId,
            'status' => 'completed',
            'reused' => true,
            'cached' => true,
        ]);

        Queue::assertNothingPushed();
    }

    public function test_public_scan_create_does_not_reuse_completed_run_older_than_48_hours(): void
    {
        Queue::fake();

        $store = app(ScanRunStore::class);
        $runId = $store->createRun(
            mode: 'username',
            targets: ['alice'],
            options: ['category' => 'social', 'store' => true],
            expandedTargets: ['alice'],
            validatorCount: 1,
            expectedResults: 1,
            selectedValidatorKeys: ['alpha'],
        );
        $store->markJobStarted($runId);
        $store->appendResult($runId, [
            'target' => 'alice',
            'category' => 'social',
            'site_name' => 'Alpha',
            'url' => 'https://alpha.test',
            'status' => 'Found',
            'reason' => '',
            'mode' => 'username',
            'key' => 'alpha',
        ], 0, 0);

        \Illuminate\Support\Facades\DB::table('scan_runs')->where('id', $runId)->update([
            'created_at' => now()->subHours(49),
            'updated_at' => now()->subHours(49),
            'completed_at' => now()->subHours(49),
        ]);

        $response = $this->postJson('/api/v1/scan', [
            'mode' => 'username',
            'target' => 'alice',
            'category' => 'social',
            'store' => true,
        ]);

        $response->assertAccepted()->assertJson([
            'ok' => true,
            'status' => 'queued',
            'reused' => false,
            'cached' => false,
        ]);

        $this->assertNotSame($runId, $response->json('run_id'));
        Queue::assertPushed(RunValidatorJob::class);
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
            'extra' => "Masked phone: ***1234\nLogin methods: password",
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
        $this->assertSame("Masked phone: ***1234\nLogin methods: password", $results[0]['extra']);
    }

    public function test_public_final_endpoint_returns_accepted_until_run_is_complete(): void
    {
        $store = app(ScanRunStore::class);
        $runId = $store->createRun(
            mode: 'username',
            targets: ['alice'],
            options: [],
            expandedTargets: ['alice'],
            validatorCount: 1,
            expectedResults: 1,
            selectedValidatorKeys: ['alpha'],
        );

        $response = $this->getJson("/api/v1/scan/{$runId}/final");

        $response->assertAccepted()
            ->assertJson([
                'ok' => true,
                'ready' => false,
                'run' => [
                    'id' => $runId,
                    'status' => 'queued',
                ],
            ]);
        $response->assertJsonMissingPath('results');
    }

    public function test_public_final_endpoint_returns_results_only_after_completion(): void
    {
        $store = app(ScanRunStore::class);
        $runId = $store->createRun(
            mode: 'username',
            targets: ['alice'],
            options: [],
            expandedTargets: ['alice'],
            validatorCount: 1,
            expectedResults: 1,
            selectedValidatorKeys: ['alpha'],
        );

        $store->markJobStarted($runId);
        $store->appendResult($runId, [
            'target' => 'alice',
            'category' => 'social',
            'site_name' => 'Alpha',
            'url' => 'https://alpha.test',
            'status' => 'Found',
            'reason' => '',
            'extra' => 'Name: Alice Example',
            'mode' => 'username',
            'key' => 'alpha',
        ], 0, 0);

        $response = $this->getJson("/api/v1/scan/{$runId}/final");

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'ready' => true,
                'run' => [
                    'id' => $runId,
                    'status' => 'completed',
                ],
            ]);
        $response->assertJsonCount(1, 'results');
        $this->assertSame('alpha', $response->json('results.0.key'));
    }

    public function test_public_final_endpoint_returns_conflict_for_failed_runs(): void
    {
        $store = app(ScanRunStore::class);
        $runId = $store->createRun(
            mode: 'username',
            targets: ['alice'],
            options: [],
            expandedTargets: ['alice'],
            validatorCount: 1,
            expectedResults: 1,
            selectedValidatorKeys: ['alpha'],
        );

        $store->failRun($runId, 'Queue dispatch failed');

        $response = $this->getJson("/api/v1/scan/{$runId}/final");

        $response->assertStatus(409)
            ->assertJson([
                'ok' => false,
                'ready' => false,
                'error' => 'Queue dispatch failed',
                'run' => [
                    'id' => $runId,
                    'status' => 'failed',
                ],
            ]);
    }

    public function test_public_scan_status_returns_structured_metadata_fields(): void
    {
        $store = app(ScanRunStore::class);
        $runId = $store->createRun(
            mode: 'username',
            targets: ['alice'],
            options: [],
            expandedTargets: ['alice'],
            validatorCount: 1,
            expectedResults: 1,
            selectedValidatorKeys: ['alpha'],
        );

        $store->markJobStarted($runId);
        $store->appendResult($runId, [
            'target' => 'alice',
            'category' => 'social',
            'site_name' => 'Alpha',
            'url' => 'https://alpha.test',
            'status' => 'Found',
            'reason' => '',
            'extra' => 'Name: Alice Example',
            'mode' => 'username',
            'key' => 'alpha',
            'platform' => 'alpha',
            'normalized_status' => 'found',
            'profile_url' => 'https://alpha.test/users/alice',
            'confidence' => 0.97,
            'metadata' => [
                'display_name' => 'Alice Example',
                'username' => 'alice',
                'external_links' => ['https://portfolio.test'],
                'status_detail' => 'found',
                'observed_metadata_level' => 4,
                'evidence' => ['profile_url', 'display_name'],
            ],
            'external_links' => ['https://portfolio.test'],
            'error' => null,
        ], 0, 0);

        $response = $this->getJson("/api/v1/scan/{$runId}");

        $response->assertOk();
        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertSame('found', $results[0]['normalized_status']);
        $this->assertSame('https://alpha.test/users/alice', $results[0]['profile_url']);
        $this->assertSame(0.97, $results[0]['confidence']);
        $this->assertSame('Alice Example', $results[0]['metadata']['display_name']);
        $this->assertSame(['https://portfolio.test'], $results[0]['external_links']);
        $this->assertSame('found', $results[0]['normalized']['status_detail']);
        $this->assertSame(4, $results[0]['normalized']['metadata_level']);
        $this->assertContains('profile_url', $results[0]['normalized']['evidence']);
        $this->assertContains('display_name', $results[0]['normalized']['evidence']);
    }

    public function test_public_scan_status_returns_normalized_not_found_fields(): void
    {
        $store = app(ScanRunStore::class);
        $runId = $store->createRun(
            mode: 'username',
            targets: ['alice'],
            options: [],
            expandedTargets: ['alice'],
            validatorCount: 1,
            expectedResults: 1,
            selectedValidatorKeys: ['beta'],
        );

        $store->markJobStarted($runId);
        $store->appendResult($runId, [
            'target' => 'alice',
            'category' => 'social',
            'site_name' => 'Beta',
            'url' => 'https://beta.test/users/alice',
            'status' => 'Not Found',
            'reason' => '',
            'extra' => '',
            'mode' => 'username',
            'key' => 'beta',
        ], 0, 0);

        $response = $this->getJson("/api/v1/scan/{$runId}");

        $response->assertOk();
        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertSame('not_found', $results[0]['normalized_status']);
        $this->assertNull($results[0]['profile_url']);
        $this->assertSame(0.95, $results[0]['confidence']);
        $this->assertSame('alice', $results[0]['metadata']['username']);
        $this->assertSame('not_found', $results[0]['metadata']['status_detail']);
        $this->assertSame(1, $results[0]['normalized']['metadata_level']);
        $this->assertEmpty($results[0]['normalized']['evidence']);
        $this->assertNull($results[0]['normalized']['error']);
    }

    public function test_public_modules_endpoint_returns_categories_and_modules(): void
    {
        $summary = app(MetadataCapabilityService::class)->summary();

        $response = $this->getJson('/api/v1/scan/modules/username');

        $response->assertOk()->assertJson([
            'ok' => true,
            'mode' => 'username',
        ]);

        $response->assertJsonPath('metadata_summary.documented_modules', $summary['documented_modules']);
        $response->assertJsonPath('metadata_summary.validated_modules', $summary['validated_modules']);
        $response->assertJsonPath('metadata_summary.validated_level_3_plus', $summary['validated_level_3_plus']);
        $response->assertJsonPath('metadata_summary.validated_level_4', $summary['validated_level_4']);
        $response->assertJsonPath('modules.0.metadata_capability_level', 1);
        $response->assertJsonPath('modules.0.metadata_capability_strategy', 'unknown');
        $response->assertJsonPath('modules.0.metadata_validated_level', null);
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
