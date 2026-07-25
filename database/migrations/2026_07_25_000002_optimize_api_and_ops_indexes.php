<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_runs', function (Blueprint $table): void {
            $table->index('created_at', 'scan_runs_created_at_index');
            $table->index(['status', 'created_at'], 'scan_runs_status_created_at_index');
            $table->index(['status', 'updated_at'], 'scan_runs_status_updated_at_index');
            $table->index(['status', 'completed_at'], 'scan_runs_status_completed_at_index');
            $table->index(['mode', 'status', 'created_at'], 'scan_runs_mode_status_created_at_index');
            $table->index(['mode', 'status', 'completed_at'], 'scan_runs_mode_status_completed_at_index');
        });

        Schema::table('public_scan_request_events', function (Blueprint $table): void {
            $table->dropIndex('public_scan_request_events_created_at_ok_index');
            $table->dropIndex('public_scan_request_events_created_at_reused_index');
            $table->dropIndex('public_scan_request_events_created_at_cached_index');
            $table->index(
                ['ok', 'created_at', 'reused', 'cached'],
                'public_scan_events_metrics_index'
            );
        });

        Schema::table('jobs', function (Blueprint $table): void {
            $table->dropIndex('jobs_queue_index');
            $table->index(['queue', 'reserved_at'], 'jobs_queue_reserved_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table): void {
            $table->dropIndex('jobs_queue_reserved_at_index');
            $table->index('queue', 'jobs_queue_index');
        });

        Schema::table('public_scan_request_events', function (Blueprint $table): void {
            $table->dropIndex('public_scan_events_metrics_index');
            $table->index(['created_at', 'ok']);
            $table->index(['created_at', 'reused']);
            $table->index(['created_at', 'cached']);
        });

        Schema::table('scan_runs', function (Blueprint $table): void {
            $table->dropIndex('scan_runs_created_at_index');
            $table->dropIndex('scan_runs_status_created_at_index');
            $table->dropIndex('scan_runs_status_updated_at_index');
            $table->dropIndex('scan_runs_status_completed_at_index');
            $table->dropIndex('scan_runs_mode_status_created_at_index');
            $table->dropIndex('scan_runs_mode_status_completed_at_index');
        });
    }
};
