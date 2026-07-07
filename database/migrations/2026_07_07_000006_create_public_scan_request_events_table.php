<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_scan_request_events', function (Blueprint $table): void {
            $table->id();
            $table->string('run_id', 32)->nullable()->index();
            $table->string('mode', 20);
            $table->string('category')->nullable();
            $table->string('target_hash', 64);
            $table->boolean('ok')->default(true);
            $table->boolean('reused')->default(false);
            $table->boolean('cached')->default(false);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'ok']);
            $table->index(['created_at', 'reused']);
            $table->index(['created_at', 'cached']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_scan_request_events');
    }
};
