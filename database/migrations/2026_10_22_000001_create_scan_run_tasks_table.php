<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_run_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('scan_run_id', 32);
            $table->string('queue');
            $table->json('payload');
            $table->unsignedBigInteger('available_at');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->foreign('scan_run_id')->references('id')->on('scan_runs')->cascadeOnDelete();
            $table->index(['scan_run_id', 'dispatched_at', 'id'], 'scan_tasks_dispatch_index');
            $table->index(['queue', 'dispatched_at'], 'scan_tasks_backlog_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_run_tasks');
    }
};
