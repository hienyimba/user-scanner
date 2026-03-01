<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_batches', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at'], 'scan_batches_user_created_idx');
            $table->index(['status', 'created_at'], 'scan_batches_status_created_idx');
        });

        Schema::table('scan_results', function (Blueprint $table): void {
            $table->index(['scan_batch_id', 'status'], 'scan_results_batch_status_idx');
            $table->index(['created_at', 'connector_key'], 'scan_results_created_connector_idx');
        });
    }

    public function down(): void
    {
        Schema::table('scan_batches', function (Blueprint $table): void {
            $table->dropIndex('scan_batches_user_created_idx');
            $table->dropIndex('scan_batches_status_created_idx');
        });

        Schema::table('scan_results', function (Blueprint $table): void {
            $table->dropIndex('scan_results_batch_status_idx');
            $table->dropIndex('scan_results_created_connector_idx');
        });
    }
};
