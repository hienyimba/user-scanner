<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ScanRunResultPruningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_deletes_only_results_strictly_older_than_seven_days(): void
    {
        Carbon::setTestNow('2026-07-25 12:00:00');

        $this->insertRun('retentiontestrun0000000000000001');
        $this->insertResult('retentiontestrun0000000000000001', 'oldest', now()->subDays(10));
        $this->insertResult('retentiontestrun0000000000000001', 'old', now()->subDays(7)->subSecond());
        $this->insertResult('retentiontestrun0000000000000001', 'boundary', now()->subDays(7));
        $this->insertResult('retentiontestrun0000000000000001', 'recent', now()->subDay());

        $this->artisan('scanner:prune-results', ['--chunk' => 1])
            ->expectsOutputToContain('Pruned 2 scan result rows')
            ->assertSuccessful();

        $this->assertDatabaseMissing('scan_run_results', ['target' => 'oldest']);
        $this->assertDatabaseMissing('scan_run_results', ['target' => 'old']);
        $this->assertDatabaseHas('scan_run_results', ['target' => 'boundary']);
        $this->assertDatabaseHas('scan_run_results', ['target' => 'recent']);
        $this->assertDatabaseHas('scan_runs', ['id' => 'retentiontestrun0000000000000001']);
    }

    public function test_pruning_command_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('scanner:prune-results')
            ->assertSuccessful();
    }

    private function insertRun(string $id): void
    {
        DB::table('scan_runs')->insert([
            'id' => $id,
            'mode' => 'username',
            'status' => 'completed',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);
    }

    private function insertResult(string $runId, string $target, Carbon $createdAt): void
    {
        DB::table('scan_run_results')->insert([
            'scan_run_id' => $runId,
            'target' => $target,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
