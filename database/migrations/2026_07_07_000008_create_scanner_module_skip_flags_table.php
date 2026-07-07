<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanner_module_skip_flags', function (Blueprint $table): void {
            $table->id();
            $table->string('mode', 20);
            $table->string('module_key', 100);
            $table->string('duration', 20);
            $table->text('reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['mode', 'module_key']);
            $table->index(['mode', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanner_module_skip_flags');
    }
};
