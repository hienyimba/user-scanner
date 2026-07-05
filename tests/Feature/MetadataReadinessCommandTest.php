<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Scanner\MetadataCapabilityService;
use Tests\TestCase;

final class MetadataReadinessCommandTest extends TestCase
{
    public function test_metadata_readiness_command_writes_report_and_uses_current_inventory_summary(): void
    {
        $outputPath = storage_path('framework/testing/metadata-readiness-report.json');
        @unlink($outputPath);

        $summary = app(MetadataCapabilityService::class)->summary();

        $this->artisan('scanner:metadata-readiness', [
            '--output' => $outputPath,
        ])
            ->expectsOutputToContain('Wrote metadata readiness report')
            ->assertExitCode(0);

        $this->assertFileExists($outputPath);

        $report = json_decode((string) file_get_contents($outputPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($summary['documented_modules'], $report['summary']['documented_modules']);
        $this->assertSame($summary['level_3_plus'], $report['summary']['level_3_plus']);
        $this->assertSame($summary['level_4'], $report['summary']['level_4']);
        $this->assertSame($summary['validated_modules'], $report['summary']['validated_modules']);
        $this->assertSame($summary['validated_level_3_plus'], $report['summary']['validated_level_3_plus']);
        $this->assertSame($summary['validated_level_4'], $report['summary']['validated_level_4']);
        $this->assertGreaterThan(0, $report['mode_summary']['username']['documented_modules']);
        $this->assertGreaterThan(0, $report['mode_summary']['email']['documented_modules']);
        $this->assertSame(250, $report['thresholds']['min_documented']);
        $this->assertSame(150, $report['thresholds']['min_level3']);
        $this->assertSame(50, $report['thresholds']['min_level4']);
        $this->assertSame(0, $report['thresholds']['min_validated_level3']);
        $this->assertSame(50, $report['thresholds']['min_validated_level4']);
    }

    public function test_metadata_readiness_command_can_fail_threshold_assertions_for_ci_gating(): void
    {
        $this->artisan('scanner:metadata-readiness', [
            '--min-validated-level3' => 100000,
        ])
            ->expectsOutputToContain('Validated level 3+ modules')
            ->assertExitCode(1);
    }
}
