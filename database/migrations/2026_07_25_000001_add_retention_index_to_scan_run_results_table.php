<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_run_results', function (Blueprint $table): void {
            $table->index(
                ['created_at', 'id'],
                'scan_run_results_created_at_id_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('scan_run_results', function (Blueprint $table): void {
            $table->dropIndex('scan_run_results_created_at_id_index');
        });
    }
};
