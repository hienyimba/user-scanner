<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use App\Services\Scanner\MetadataAuditService;
use App\Services\Scanner\MetadataBaselineValidationService;
use App\Services\Scanner\MetadataCapabilityService;
use App\Services\Scanner\ScannerEngineService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class MetadataBaselineValidationCommandTest extends TestCase
{
    private string $registryPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->swapValidators(
            new MetadataBaselineFakeValidator(
                key: 'rich-profile',
                category: 'dev',
                mode: 'username',
                siteName: 'Rich Profile',
                siteUrl: 'https://profiles.test/users/{user}',
                result: ScanResult::fromArray([
                    'target' => 'alice',
                    'category' => 'dev',
                    'site_name' => 'Rich Profile',
                    'url' => 'https://profiles.test',
                    'status' => 'Taken',
                    'extra' => "Name: Alice Example\nFollowers: 1.2k",
                    'mode' => 'username',
                    'key' => 'rich-profile',
                ]),
            ),
            new MetadataBaselineFakeValidator(
                key: 'blocked-profile',
                category: 'dev',
                mode: 'username',
                siteName: 'Blocked Profile',
                siteUrl: 'https://blocked.test/users/{user}',
                result: ScanResult::fromArray([
                    'target' => 'alice',
                    'category' => 'dev',
                    'site_name' => 'Blocked Profile',
                    'url' => 'https://blocked.test',
                    'status' => 'Error',
                    'reason' => 'blocked-profile: anti-bot challenge detected',
                    'mode' => 'username',
                    'key' => 'blocked-profile',
                ]),
            ),
        );

        $this->registryPath = storage_path('framework/testing/metadata-baseline-targets.php');
        file_put_contents(
            $this->registryPath,
            <<<'PHP'
            <?php

            return [
                'username' => [
                    'rich-profile' => ['alice'],
                    'blocked-profile' => ['alice'],
                ],
                'email' => [],
            ];
            PHP
        );

        $this->app->instance(
            MetadataBaselineValidationService::class,
            new MetadataBaselineValidationService(
                $this->app->make(MetadataAuditService::class),
                $this->app->make(MetadataCapabilityService::class),
                $this->app->make(\App\Services\Scanner\MetadataTargetResolver::class),
                $this->registryPath,
            )
        );
    }

    public function test_metadata_baseline_validation_command_writes_report_and_overlay(): void
    {
        Http::fake([
            'https://profiles.test/users/alice' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <meta property="og:title" content="Alice Example">
                    <meta property="og:description" content="Builder and researcher">
                    <meta property="og:image" content="https://cdn.test/alice.jpg">
                </head>
                <body>
                    <a href="https://portfolio.test">Portfolio</a>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $reportPath = storage_path('framework/testing/metadata-baseline-validation.json');
        $overlayPath = storage_path('framework/testing/proposed-metadata-overlay.php');
        @unlink($reportPath);
        @unlink($overlayPath);

        $this->artisan('scanner:metadata-validate-baselines', [
            'mode' => 'username',
            '--output' => $reportPath,
            '--export-overlay' => $overlayPath,
        ])
            ->expectsOutputToContain('Wrote metadata baseline validation report')
            ->expectsOutputToContain('Wrote proposed validation overlay')
            ->assertExitCode(0);

        $this->assertFileExists($reportPath);
        $this->assertFileExists($overlayPath);

        $report = json_decode((string) file_get_contents($reportPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('username', $report['mode']);
        $this->assertSame(2, $report['summary']['modules_requested']);
        $this->assertSame(1, $report['summary']['modules_with_proposed_validation']);
        $this->assertSame(1, $report['summary']['stable_modules']);
        $this->assertSame(1, $report['summary']['blocked_modules']);
        $this->assertSame(0, $report['summary']['partial_modules']);
        $this->assertSame(0, $report['summary']['inconclusive_modules']);
        $this->assertSame(0, $report['summary']['broken_modules']);
        $this->assertSame(1, $report['summary']['successful_targets']);
        $this->assertSame(1, $report['summary']['failed_targets']);

        $rich = collect($report['modules'])->firstWhere('module', 'rich-profile');
        $this->assertNotNull($rich);
        $this->assertSame(4, $rich['proposed_validated_level']);
        $this->assertSame('stable', $rich['validation_status']);
        $this->assertSame(['alice'], $rich['successful_targets']);

        $blocked = collect($report['modules'])->firstWhere('module', 'blocked-profile');
        $this->assertNotNull($blocked);
        $this->assertNull($blocked['proposed_validated_level']);
        $this->assertSame('blocked', $blocked['validation_status']);
        $this->assertSame(['alice'], $blocked['failed_targets']);

        /** @var array<string, mixed> $overlay */
        $overlay = require $overlayPath;
        $this->assertSame(4, $overlay['username']['rich-profile']['validated_level']);
        $this->assertSame(['alice'], $overlay['username']['rich-profile']['targets']);
        $this->assertArrayNotHasKey('blocked-profile', $overlay['username']);
    }

    public function test_email_baseline_validation_preserves_private_target_aliases_in_reports_and_overlay(): void
    {
        config()->set('scanner_private_targets.email', [
            'baseline_email_primary' => 'resolved@example.com',
        ]);

        $this->swapValidators(
            new MetadataBaselineFakeValidator(
                key: 'email-rich',
                category: 'dev',
                mode: 'email',
                siteName: 'Email Rich',
                siteUrl: 'https://email-rich.test',
                result: ScanResult::fromArray([
                    'target' => 'resolved@example.com',
                    'category' => 'dev',
                    'site_name' => 'Email Rich',
                    'url' => 'https://email-rich.test',
                    'status' => 'Registered',
                    'mode' => 'email',
                    'key' => 'email-rich',
                    'confidence' => 0.96,
                    'metadata' => [
                        'public_email' => 'resolved@example.com',
                        'sources' => ['api_json'],
                        'evidence' => ['public_email', 'api_json'],
                        'observed_metadata_level' => 4,
                    ],
                ]),
            ),
        );

        file_put_contents(
            $this->registryPath,
            <<<'PHP'
            <?php

            return [
                'username' => [],
                'email' => [
                    'email-rich' => ['baseline_email_primary'],
                ],
            ];
            PHP
        );

        $this->app->instance(
            MetadataBaselineValidationService::class,
            new MetadataBaselineValidationService(
                $this->app->make(MetadataAuditService::class),
                $this->app->make(MetadataCapabilityService::class),
                $this->app->make(\App\Services\Scanner\MetadataTargetResolver::class),
                $this->registryPath,
            )
        );

        $reportPath = storage_path('framework/testing/metadata-baseline-validation-email.json');
        $overlayPath = storage_path('framework/testing/proposed-metadata-email-overlay.php');
        @unlink($reportPath);
        @unlink($overlayPath);

        $this->artisan('scanner:metadata-validate-baselines', [
            'mode' => 'email',
            '--output' => $reportPath,
            '--export-overlay' => $overlayPath,
        ])->assertExitCode(0);

        $report = json_decode((string) file_get_contents($reportPath), true, 512, JSON_THROW_ON_ERROR);
        $module = collect($report['modules'])->firstWhere('module', 'email-rich');
        $this->assertNotNull($module);
        $this->assertSame('stable', $module['validation_status']);
        $this->assertSame(['baseline_email_primary'], $module['successful_targets']);
        $this->assertSame([], $module['failed_targets']);
        $this->assertSame('baseline_email_primary', $module['results'][0]['target']);
        $this->assertSame('baseline_email_primary', $module['results'][0]['normalized']['target']);

        /** @var array<string, mixed> $overlay */
        $overlay = require $overlayPath;
        $this->assertSame(['baseline_email_primary'], $overlay['email']['email-rich']['targets']);
    }

    public function test_baseline_validation_marks_network_only_failures_as_inconclusive(): void
    {
        $this->swapValidators(
            new MetadataBaselineFakeValidator(
                key: 'email-network-fail',
                category: 'dev',
                mode: 'email',
                siteName: 'Email Network Fail',
                siteUrl: 'https://email-network-fail.test',
                result: ScanResult::fromArray([
                    'target' => 'resolved@example.com',
                    'category' => 'dev',
                    'site_name' => 'Email Network Fail',
                    'url' => 'https://email-network-fail.test',
                    'status' => 'Error',
                    'reason' => 'cURL error 7: Failed to connect to email-network-fail.test port 443 after 10 ms: Bad access',
                    'mode' => 'email',
                    'key' => 'email-network-fail',
                ]),
            ),
        );

        config()->set('scanner_private_targets.email', [
            'baseline_email_primary' => 'resolved@example.com',
        ]);

        file_put_contents(
            $this->registryPath,
            <<<'PHP'
            <?php

            return [
                'username' => [],
                'email' => [
                    'email-network-fail' => ['baseline_email_primary'],
                ],
            ];
            PHP
        );

        $this->app->instance(
            MetadataBaselineValidationService::class,
            new MetadataBaselineValidationService(
                $this->app->make(MetadataAuditService::class),
                $this->app->make(MetadataCapabilityService::class),
                $this->app->make(\App\Services\Scanner\MetadataTargetResolver::class),
                $this->registryPath,
            )
        );

        $reportPath = storage_path('framework/testing/metadata-baseline-validation-email-network.json');
        @unlink($reportPath);

        $this->artisan('scanner:metadata-validate-baselines', [
            'mode' => 'email',
            '--output' => $reportPath,
        ])->assertExitCode(0);

        $report = json_decode((string) file_get_contents($reportPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $report['summary']['inconclusive_modules']);
        $this->assertSame(0, $report['summary']['broken_modules']);

        $module = collect($report['modules'])->firstWhere('module', 'email-network-fail');
        $this->assertNotNull($module);
        $this->assertSame('inconclusive', $module['validation_status']);
        $this->assertNull($module['proposed_validated_level']);
    }

    protected function tearDown(): void
    {
        if (is_file($this->registryPath)) {
            unlink($this->registryPath);
        }

        parent::tearDown();
    }

    private function swapValidators(ValidatorContract ...$validators): void
    {
        $this->app->instance('scanner.validators', $validators);
        $this->app->forgetInstance(ScannerEngineService::class);
        $this->app->forgetInstance(MetadataAuditService::class);
        $this->app->forgetInstance(MetadataBaselineValidationService::class);
    }
}

final class MetadataBaselineFakeValidator implements ValidatorContract
{
    public function __construct(
        private readonly string $key,
        private readonly string $category,
        private readonly string $mode,
        private readonly string $siteName,
        private readonly string $siteUrl,
        private readonly ScanResult $result,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function siteName(): string
    {
        return $this->siteName;
    }

    public function siteUrl(): string
    {
        return $this->siteUrl;
    }

    public function check(string $target, array $options = []): ScanResult
    {
        return $this->result;
    }
}
