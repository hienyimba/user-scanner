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
            $table->string('platform', 100)->nullable()->after('key');
            $table->string('normalized_status', 32)->nullable()->after('platform');
            $table->text('profile_url')->nullable()->after('normalized_status');
            $table->float('confidence')->nullable()->after('profile_url');
            $table->json('metadata')->nullable()->after('confidence');
            $table->json('external_links')->nullable()->after('metadata');
            $table->text('error')->nullable()->after('external_links');
        });
    }

    public function down(): void
    {
        Schema::table('scan_run_results', function (Blueprint $table): void {
            $table->dropColumn([
                'platform',
                'normalized_status',
                'profile_url',
                'confidence',
                'metadata',
                'external_links',
                'error',
            ]);
        });
    }
};
