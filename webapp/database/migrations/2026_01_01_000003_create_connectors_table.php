<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connectors', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('name', 120);
            $table->string('category', 80);
            $table->boolean('supports_username')->default(false);
            $table->boolean('supports_email')->default(false);
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('timeout_seconds')->default(20);
            $table->unsignedTinyInteger('retry_limit')->default(3);
            $table->string('health_status', 32)->default('unknown');
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connectors');
    }
};
