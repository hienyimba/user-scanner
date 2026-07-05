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
        $this->assertSame(1, $report['summary']['successful_targets']);
        $this->assertSame(1, $report['summary']['failed_targets']);

        $rich = collect($report['modules'])->firstWhere('module', 'rich-profile');
        $this->assertNotNull($rich);
        $this->assertSame(4, $rich['proposed_validated_level']);
        $this->assertSame(['alice'], $rich['successful_targets']);

        $blocked = collect($report['modules'])->firstWhere('module', 'blocked-profile');
        $this->assertNotNull($blocked);
        $this->assertNull($blocked['proposed_validated_level']);
        $this->assertSame(['alice'], $blocked['failed_targets']);

        /** @var array<string, mixed> $overlay */
        $overlay = require $overlayPath;
        $this->assertSame(4, $overlay['username']['rich-profile']['validated_level']);
        $this->assertSame(['alice'], $overlay['username']['rich-profile']['targets']);
        $this->assertArrayNotHasKey('blocked-profile', $overlay['username']);
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
