<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_queue_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('queue_name', 100);
            $table->unsignedInteger('queued_jobs')->default(0);
            $table->unsignedInteger('reserved_jobs')->default(0);
            $table->unsignedInteger('active_runs')->default(0);
            $table->unsignedInteger('queued_runs')->default(0);
            $table->unsignedInteger('running_runs')->default(0);
            $table->unsignedInteger('outstanding_results')->default(0);
            $table->timestamps();

            $table->index(['queue_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_queue_snapshots');
    }
};
