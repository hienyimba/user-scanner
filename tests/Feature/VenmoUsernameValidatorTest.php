<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Scanner\ScannerEngineService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class VenmoUsernameValidatorTest extends TestCase
{
    public function test_venmo_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            'https://api.venmo.com/v1/users/eodioko' => Http::response([
                'id' => 424242,
                'username' => 'eodioko',
                'first_name' => 'Eghosa',
                'last_name' => 'Odioko',
                'about' => 'Building better open-source security tooling.',
                'identity_type' => 'personal',
                'is_business' => false,
                'date_joined' => '2020-01-02T03:04:05Z',
                'profile_picture_url' => 'https://images.example/venmo-avatar.jpg',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'venmo',
            target: 'eodioko',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://venmo.com/eodioko', $result->profileUrl);
        $this->assertSame('Eghosa Odioko', $result->metadata['display_name']);
        $this->assertSame('eodioko', $result->metadata['username']);
        $this->assertSame('Building better open-source security tooling.', $result->metadata['bio']);
        $this->assertSame('https://images.example/venmo-avatar.jpg', $result->metadata['avatar_url']);
        $this->assertSame(424242, $result->metadata['venmo_user_id']);
        $this->assertSame('personal', $result->metadata['account_type']);
        $this->assertFalse((bool) $result->metadata['is_business']);
        $this->assertSame('2020-01-02T03:04:05+00:00', $result->metadata['created_at']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_venmo_baseline_validation_uses_eodioko_and_proposes_level_four_overlay(): void
    {
        Http::fake([
            'https://api.venmo.com/v1/users/eodioko' => Http::response([
                'id' => 424242,
                'username' => 'eodioko',
                'first_name' => 'Eghosa',
                'last_name' => 'Odioko',
                'about' => 'Building better open-source security tooling.',
                'identity_type' => 'personal',
                'is_business' => false,
                'date_joined' => '2020-01-02T03:04:05Z',
                'profile_picture_url' => 'https://images.example/venmo-avatar.jpg',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $reportPath = storage_path('framework/testing/venmo-baseline-validation.json');
        $overlayPath = storage_path('framework/testing/venmo-baseline-overlay.php');
        @unlink($reportPath);
        @unlink($overlayPath);

        $this->artisan('scanner:metadata-validate-baselines', [
            'mode' => 'username',
            '--module' => ['venmo'],
            '--output' => $reportPath,
            '--export-overlay' => $overlayPath,
            '--enrich-metadata' => '0',
        ])
            ->expectsOutputToContain('Wrote metadata baseline validation report')
            ->expectsOutputToContain('Wrote proposed validation overlay')
            ->assertExitCode(0);

        $report = json_decode((string) file_get_contents($reportPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['venmo'], $report['requested_modules']);
        $this->assertSame(1, $report['summary']['modules_requested']);
        $this->assertSame(1, $report['summary']['modules_with_proposed_validation']);
        $this->assertSame(1, $report['summary']['stable_modules']);
        $this->assertSame(1, $report['summary']['successful_targets']);
        $this->assertSame(0, $report['summary']['failed_targets']);

        $module = $report['modules'][0];
        $this->assertSame('venmo', $module['module']);
        $this->assertSame(4, $module['documented_capability_level']);
        $this->assertSame(4, $module['current_validated_level']);
        $this->assertSame(4, $module['proposed_validated_level']);
        $this->assertSame('stable', $module['validation_status']);
        $this->assertSame(['eodioko'], $module['successful_targets']);
        $this->assertSame(['eodioko'], $module['current_validated_targets']);

        /** @var array<string, mixed> $overlay */
        $overlay = require $overlayPath;
        $this->assertSame(4, $overlay['username']['venmo']['validated_level']);
        $this->assertSame(['eodioko'], $overlay['username']['venmo']['targets']);
    }
}
