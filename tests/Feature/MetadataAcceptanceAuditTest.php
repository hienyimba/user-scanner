<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Scanner\MetadataCapabilityService;
use Tests\TestCase;

final class MetadataAcceptanceAuditTest extends TestCase
{
    public function test_webvetted_acceptance_thresholds_are_met_by_current_documented_and_validated_inventory(): void
    {
        $summary = app(MetadataCapabilityService::class)->summary();

        $this->assertGreaterThanOrEqual(250, $summary['documented_modules']);
        $this->assertGreaterThanOrEqual(150, $summary['level_3_plus']);
        $this->assertGreaterThanOrEqual(50, $summary['level_4']);
        $this->assertGreaterThanOrEqual(50, $summary['validated_level_4']);
    }

    public function test_public_modules_endpoint_exposes_documented_and_validated_capability_counters(): void
    {
        $summary = app(MetadataCapabilityService::class)->summary();

        $response = $this->getJson('/api/v1/scan/modules/username');

        $response->assertOk();
        $response->assertJsonPath('metadata_summary.documented_modules', $summary['documented_modules']);
        $response->assertJsonPath('metadata_summary.level_3_plus', $summary['level_3_plus']);
        $response->assertJsonPath('metadata_summary.level_4', $summary['level_4']);
        $response->assertJsonPath('metadata_summary.validated_modules', $summary['validated_modules']);
        $response->assertJsonPath('metadata_summary.validated_level_3_plus', $summary['validated_level_3_plus']);
        $response->assertJsonPath('metadata_summary.validated_level_4', $summary['validated_level_4']);
    }
}
