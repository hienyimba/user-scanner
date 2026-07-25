<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DatabaseIndexCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_and_ops_query_paths_have_matching_indexes(): void
    {
        $this->assertIndex('scan_runs', ['created_at']);
        $this->assertIndex('scan_runs', ['status', 'created_at']);
        $this->assertIndex('scan_runs', ['status', 'updated_at']);
        $this->assertIndex('scan_runs', ['status', 'completed_at']);
        $this->assertIndex('scan_runs', ['mode', 'status', 'created_at']);
        $this->assertIndex('scan_runs', ['mode', 'status', 'completed_at']);

        $this->assertIndex('scan_run_results', ['scan_run_id', 'target_index', 'validator_index']);
        $this->assertIndex('scan_run_results', ['scan_run_id', 'status']);
        $this->assertIndex('scan_run_results', ['scan_run_id', 'category']);
        $this->assertIndex('scan_run_results', ['created_at', 'id']);

        $this->assertIndex('public_scan_request_events', ['run_id']);
        $this->assertIndex('public_scan_request_events', ['ok', 'created_at', 'reused', 'cached']);

        $this->assertIndex('ops_queue_snapshots', ['queue_name', 'created_at']);
        $this->assertIndex('jobs', ['queue', 'reserved_at']);
        $this->assertIndex('scanner_module_skip_flags', ['mode', 'module_key'], unique: true);
        $this->assertIndex('scanner_module_skip_flags', ['mode', 'expires_at']);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function assertIndex(string $table, array $columns, bool $unique = false): void
    {
        $index = collect(Schema::getIndexes($table))
            ->first(static function (array $index) use ($columns, $unique): bool {
                return array_values($index['columns']) === $columns
                    && (! $unique || (bool) $index['unique']);
            });

        $this->assertNotNull(
            $index,
            sprintf(
                'Expected %s index on %s (%s).',
                $unique ? 'a unique' : 'an',
                $table,
                implode(', ', $columns),
            ),
        );
    }
}
