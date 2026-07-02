<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_runs', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('mode', 20);
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('target_count')->default(0);
            $table->unsignedInteger('validator_count')->default(0);
            $table->unsignedInteger('expected_results')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('queued_jobs')->default(0);
            $table->unsignedInteger('running_jobs')->default(0);
            $table->unsignedInteger('completed_jobs')->default(0);
            $table->json('targets')->nullable();
            $table->json('selected_validator_keys')->nullable();
            $table->json('options')->nullable();
            $table->json('expanded_targets')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_runs');
    }
};
