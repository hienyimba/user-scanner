<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class MetadataAcceptanceAuditCommandTest extends TestCase
{
    public function test_metadata_acceptance_audit_writes_machine_readable_report(): void
    {
        $outputPath = storage_path('framework/testing/metadata-acceptance-audit.json');
        @unlink($outputPath);

        $this->artisan('scanner:metadata-acceptance-audit', [
            '--output' => $outputPath,
        ])
            ->expectsOutputToContain('Wrote metadata acceptance audit')
            ->assertExitCode(0);

        $this->assertFileExists($outputPath);

        $report = json_decode((string) file_get_contents($outputPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('proved_with_live_level3_gap', $report['overall_status']);
        $this->assertFalse($report['strict_live_level3_required']);
        $this->assertSame(293, $report['summary']['documented_modules']);
        $this->assertSame(112, $report['summary']['validated_level_3_plus']);
        $this->assertSame(100, $report['summary']['validated_level_4']);

        $documentedLevel3 = collect($report['requirements'])->firstWhere('key', 'documented_level_3_plus');
        $this->assertNotNull($documentedLevel3);
        $this->assertSame('proved', $documentedLevel3['status']);

        $validatedLevel3 = collect($report['requirements'])->firstWhere('key', 'validated_level_3_plus');
        $this->assertNotNull($validatedLevel3);
        $this->assertSame('unproved', $validatedLevel3['status']);
    }

    public function test_metadata_acceptance_audit_can_fail_when_live_level3_is_required(): void
    {
        $this->artisan('scanner:metadata-acceptance-audit', [
            '--require-live-level3' => true,
        ])
            ->expectsOutputToContain('Live-validated Level 3+ modules >= 150 requirement is not yet proved')
            ->assertExitCode(1);
    }

    public function test_metadata_acceptance_audit_can_include_stable_revalidation_evidence(): void
    {
        $reportPath = storage_path('framework/testing/metadata-revalidation-stable.json');
        file_put_contents($reportPath, json_encode([
            'generated_at' => '2026-07-05T00:00:00+00:00',
            'mode' => 'username',
            'summary' => [
                'modules_requested' => 3,
                'stable_modules' => 3,
                'partial_modules' => 0,
                'degraded_modules' => 0,
                'blocked_modules' => 0,
                'inconclusive_modules' => 0,
                'broken_modules' => 0,
                'unstable_modules' => 0,
                'successful_targets' => 3,
                'failed_targets' => 0,
            ],
            'modules' => [],
        ], JSON_THROW_ON_ERROR));

        $outputPath = storage_path('framework/testing/metadata-acceptance-audit-with-revalidation.json');
        @unlink($outputPath);

        $this->artisan('scanner:metadata-acceptance-audit', [
            '--output' => $outputPath,
            '--revalidation-report' => $reportPath,
            '--require-stable-overlays' => true,
        ])->assertExitCode(0);

        $report = json_decode((string) file_get_contents($outputPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($report['strict_overlay_stability_required']);
        $this->assertSame(0, $report['revalidation']['summary']['unstable_modules']);

        $unstable = collect($report['requirements'])->firstWhere('key', 'validated_overlay_unstable_modules');
        $this->assertNotNull($unstable);
        $this->assertSame('proved', $unstable['status']);
    }

    public function test_metadata_acceptance_audit_can_fail_when_revalidation_report_shows_overlay_drift(): void
    {
        $reportPath = storage_path('framework/testing/metadata-revalidation-unstable.json');
        file_put_contents($reportPath, json_encode([
            'generated_at' => '2026-07-05T00:00:00+00:00',
            'mode' => 'username',
            'summary' => [
                'modules_requested' => 2,
                'stable_modules' => 0,
                'partial_modules' => 1,
                'degraded_modules' => 1,
                'blocked_modules' => 0,
                'inconclusive_modules' => 0,
                'broken_modules' => 0,
                'unstable_modules' => 2,
                'successful_targets' => 1,
                'failed_targets' => 1,
            ],
            'modules' => [],
        ], JSON_THROW_ON_ERROR));

        $this->artisan('scanner:metadata-acceptance-audit', [
            '--revalidation-report' => $reportPath,
            '--require-stable-overlays' => true,
        ])
            ->expectsOutputToContain('Revalidated unstable overlay modules <= 0 requirement failed')
            ->expectsOutputToContain('Revalidated degraded overlay modules <= 0 requirement failed')
            ->assertExitCode(1);
    }

    public function test_metadata_acceptance_audit_can_fail_when_revalidation_report_is_blocked(): void
    {
        $reportPath = storage_path('framework/testing/metadata-revalidation-blocked.json');
        file_put_contents($reportPath, json_encode([
            'generated_at' => '2026-07-05T00:00:00+00:00',
            'mode' => 'username',
            'summary' => [
                'modules_requested' => 1,
                'stable_modules' => 0,
                'partial_modules' => 0,
                'degraded_modules' => 0,
                'blocked_modules' => 1,
                'inconclusive_modules' => 0,
                'broken_modules' => 0,
                'unstable_modules' => 1,
                'successful_targets' => 0,
                'failed_targets' => 1,
            ],
            'modules' => [],
        ], JSON_THROW_ON_ERROR));

        $this->artisan('scanner:metadata-acceptance-audit', [
            '--revalidation-report' => $reportPath,
            '--require-stable-overlays' => true,
        ])
            ->expectsOutputToContain('Revalidated blocked overlay modules <= 0 requirement failed')
            ->assertExitCode(1);
    }

    public function test_metadata_acceptance_audit_can_fail_when_revalidation_report_is_inconclusive(): void
    {
        $reportPath = storage_path('framework/testing/metadata-revalidation-inconclusive.json');
        file_put_contents($reportPath, json_encode([
            'generated_at' => '2026-07-05T00:00:00+00:00',
            'mode' => 'username',
            'summary' => [
                'modules_requested' => 1,
                'stable_modules' => 0,
                'partial_modules' => 0,
                'degraded_modules' => 0,
                'inconclusive_modules' => 1,
                'broken_modules' => 0,
                'unstable_modules' => 1,
                'successful_targets' => 0,
                'failed_targets' => 1,
            ],
            'modules' => [],
        ], JSON_THROW_ON_ERROR));

        $this->artisan('scanner:metadata-acceptance-audit', [
            '--revalidation-report' => $reportPath,
            '--require-stable-overlays' => true,
        ])
            ->expectsOutputToContain('Revalidated inconclusive overlay modules <= 0 requirement failed')
            ->assertExitCode(1);
    }
}
