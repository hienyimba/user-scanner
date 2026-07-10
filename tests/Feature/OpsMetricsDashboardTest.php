<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OpsMetricsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_ops_metrics_page_renders_db_backed_vitals(): void
    {
        DB::table('scan_runs')->insert([
            [
                'id' => 'runcompleted0001',
                'mode' => 'username',
                'status' => 'completed',
                'target_count' => 1,
                'validator_count' => 2,
                'expected_results' => 2,
                'total' => 2,
                'processed' => 2,
                'queued_jobs' => 0,
                'running_jobs' => 0,
                'completed_jobs' => 2,
                'targets' => json_encode(['alice'], JSON_THROW_ON_ERROR),
                'selected_validator_keys' => json_encode(['alpha', 'beta'], JSON_THROW_ON_ERROR),
                'options' => json_encode([], JSON_THROW_ON_ERROR),
                'expanded_targets' => json_encode(['alice'], JSON_THROW_ON_ERROR),
                'error' => null,
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subMinutes(70),
                'completed_at' => now()->subMinutes(70),
            ],
            [
                'id' => 'runfailed0000002',
                'mode' => 'username',
                'status' => 'failed',
                'target_count' => 1,
                'validator_count' => 1,
                'expected_results' => 1,
                'total' => 1,
                'processed' => 0,
                'queued_jobs' => 0,
                'running_jobs' => 0,
                'completed_jobs' => 0,
                'targets' => json_encode(['bob'], JSON_THROW_ON_ERROR),
                'selected_validator_keys' => json_encode(['gamma'], JSON_THROW_ON_ERROR),
                'options' => json_encode([], JSON_THROW_ON_ERROR),
                'expanded_targets' => json_encode(['bob'], JSON_THROW_ON_ERROR),
                'error' => 'timeout',
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subMinutes(50),
                'completed_at' => null,
            ],
        ]);

        DB::table('scan_run_results')->insert([
            [
                'scan_run_id' => 'runcompleted0001',
                'target' => 'alice',
                'category' => 'social',
                'site_name' => 'Alpha',
                'url' => 'https://alpha.test',
                'status' => 'Error',
                'reason' => 'timeout',
                'extra' => '',
                'mode' => 'username',
                'key' => 'alpha',
                'target_index' => 0,
                'validator_index' => 0,
                'created_at' => now()->subMinutes(90),
                'updated_at' => now()->subMinutes(90),
            ],
            [
                'scan_run_id' => 'runcompleted0001',
                'target' => 'alice',
                'category' => 'social',
                'site_name' => 'Beta',
                'url' => 'https://beta.test',
                'status' => 'Found',
                'reason' => '',
                'extra' => '',
                'mode' => 'username',
                'key' => 'beta',
                'target_index' => 0,
                'validator_index' => 1,
                'created_at' => now()->subMinutes(89),
                'updated_at' => now()->subMinutes(89),
            ],
        ]);

        DB::table('public_scan_request_events')->insert([
            [
                'run_id' => 'runcompleted0001',
                'mode' => 'username',
                'category' => 'social',
                'target_hash' => hash('sha256', 'alice'),
                'ok' => true,
                'reused' => true,
                'cached' => true,
                'error' => null,
                'created_at' => now()->subMinutes(88),
                'updated_at' => now()->subMinutes(88),
            ],
            [
                'run_id' => 'runfailed0000002',
                'mode' => 'username',
                'category' => 'social',
                'target_hash' => hash('sha256', 'bob'),
                'ok' => true,
                'reused' => false,
                'cached' => false,
                'error' => null,
                'created_at' => now()->subMinutes(87),
                'updated_at' => now()->subMinutes(87),
            ],
        ]);

        DB::table('ops_queue_snapshots')->insert([
            'queue_name' => 'scanner',
            'queued_jobs' => 4,
            'reserved_jobs' => 2,
            'active_runs' => 3,
            'queued_runs' => 1,
            'running_runs' => 2,
            'outstanding_results' => 9,
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        $response = $this->get(route('ops.metrics'));

        $response->assertOk();
        $response->assertSee('Ops Dashboard');
        $response->assertSee('Completion Rate');
        $response->assertSee('P95 Time To Final Result');
        $response->assertSee('Cache / Reuse Hit Rate');
        $response->assertSee('Validator Error Rate %');
        $response->assertSee('validator-error-rate-chart');
        $response->assertSee('Validator Error Rate By Module');
        $response->assertSee('Storage Growth');
        $response->assertSee('Queue Backlog');
        $response->assertSee('30-day window');
        $response->assertSee('7-day window');
        $response->assertSee('1-day window');
        $response->assertSee('6hrs');
    }

    public function test_ops_metrics_page_supports_short_window_presets(): void
    {
        DB::table('ops_queue_snapshots')->insert([
            'queue_name' => 'scanner',
            'queued_jobs' => 1,
            'reserved_jobs' => 0,
            'active_runs' => 1,
            'queued_runs' => 1,
            'running_runs' => 0,
            'outstanding_results' => 2,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $response = $this->get(route('ops.metrics', ['window' => '6h']));

        $response->assertOk();
        $response->assertSee('Hourly buckets');
        $response->assertSee(route('ops.metrics', ['window' => '30d']), false);
        $response->assertSee(route('ops.metrics', ['window' => '6h']), false);
    }
}
