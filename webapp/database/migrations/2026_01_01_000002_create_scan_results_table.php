<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scan_batch_id')->constrained()->cascadeOnDelete();
            $table->string('connector_key', 120);
            $table->string('category', 80)->nullable();
            $table->string('site_name', 120)->nullable();
            $table->string('status', 32);
            $table->text('reason')->nullable();
            $table->string('checked_url', 2048)->nullable();
            $table->string('confidence', 16)->default('mid');
            $table->json('response_metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'category']);
            $table->index('connector_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_results');
    }
};
