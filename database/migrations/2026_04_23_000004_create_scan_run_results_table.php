<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_run_results', function (Blueprint $table): void {
            $table->id();
            $table->string('scan_run_id', 32);
            $table->string('target');
            $table->string('category')->nullable();
            $table->string('site_name')->nullable();
            $table->text('url')->nullable();
            $table->string('status', 32)->nullable();
            $table->text('reason')->nullable();
            $table->text('extra')->nullable();
            $table->string('mode', 20)->nullable();
            $table->string('key', 100)->nullable();
            $table->unsignedInteger('target_index')->default(0);
            $table->unsignedInteger('validator_index')->default(0);
            $table->timestamps();

            $table->foreign('scan_run_id')->references('id')->on('scan_runs')->cascadeOnDelete();
            $table->index(['scan_run_id', 'target_index', 'validator_index']);
            $table->index(['scan_run_id', 'status']);
            $table->index(['scan_run_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_run_results');
    }
};
