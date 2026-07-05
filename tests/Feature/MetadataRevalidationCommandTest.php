<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use App\Services\Scanner\MetadataAuditService;
use App\Services\Scanner\MetadataCapabilityService;
use App\Services\Scanner\MetadataRevalidationService;
use App\Services\Scanner\ScannerEngineService;
use Tests\TestCase;

final class MetadataRevalidationCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->swapValidators(
            new MetadataRevalidationFakeValidator(
                key: 'stable-profile',
                category: 'dev',
                mode: 'username',
                siteName: 'Stable Profile',
                siteUrl: 'https://profiles.test/users/{user}',
                resultsByTarget: [
                    'alice' => ScanResult::fromArray([
                        'target' => 'alice',
                        'category' => 'dev',
                        'site_name' => 'Stable Profile',
                        'url' => 'https://profiles.test/users/alice',
                        'status' => 'Taken',
                        'mode' => 'username',
                        'key' => 'stable-profile',
                        'metadata' => [
                            'display_name' => 'Alice Example',
                            'bio' => 'Builder',
                            'followers' => 1200,
                            'external_links' => ['https://alice.test'],
                        ],
                    ]),
                ],
            ),
            new MetadataRevalidationFakeValidator(
                key: 'partial-profile',
                category: 'dev',
                mode: 'username',
                siteName: 'Partial Profile',
                siteUrl: 'https://profiles.test/users/{user}',
                resultsByTarget: [
                    'bob' => ScanResult::fromArray([
                        'target' => 'bob',
                        'category' => 'dev',
                        'site_name' => 'Partial Profile',
                        'url' => 'https://profiles.test/users/bob',
                        'status' => 'Taken',
                        'mode' => 'username',
                        'key' => 'partial-profile',
                        'metadata' => [
                            'display_name' => 'Bob Example',
                            'bio' => 'Maintainer',
                            'followers' => 88,
                            'external_links' => ['https://bob.test'],
                        ],
                    ]),
                    'charlie' => ScanResult::fromArray([
                        'target' => 'charlie',
                        'category' => 'dev',
                        'site_name' => 'Partial Profile',
                        'url' => 'https://profiles.test/users/charlie',
                        'status' => 'Error',
                        'reason' => 'partial-profile: anti-bot challenge detected',
                        'mode' => 'username',
                        'key' => 'partial-profile',
                    ]),
                ],
            ),
            new MetadataRevalidationFakeValidator(
                key: 'degraded-profile',
                category: 'dev',
                mode: 'username',
                siteName: 'Degraded Profile',
                siteUrl: 'https://profiles.test/users/{user}',
                resultsByTarget: [
                    'dana' => ScanResult::fromArray([
                        'target' => 'dana',
                        'category' => 'dev',
                        'site_name' => 'Degraded Profile',
                        'url' => 'https://profiles.test/users/dana',
                        'status' => 'Taken',
                        'mode' => 'username',
                        'key' => 'degraded-profile',
                    ]),
                ],
            ),
            new MetadataRevalidationFakeValidator(
                key: 'blocked-profile',
                category: 'dev',
                mode: 'username',
                siteName: 'Blocked Profile',
                siteUrl: 'https://profiles.test/users/{user}',
                resultsByTarget: [
                    'erin' => ScanResult::fromArray([
                        'target' => 'erin',
                        'category' => 'dev',
                        'site_name' => 'Blocked Profile',
                        'url' => 'https://profiles.test/users/erin',
                        'status' => 'Error',
                        'reason' => 'blocked-profile: blocked/rate-limited (HTTP 403)',
                        'mode' => 'username',
                        'key' => 'blocked-profile',
                    ]),
                ],
            ),
            new MetadataRevalidationFakeValidator(
                key: 'broken-profile',
                category: 'dev',
                mode: 'username',
                siteName: 'Broken Profile',
                siteUrl: 'https://profiles.test/users/{user}',
                resultsByTarget: [
                    'gina' => ScanResult::fromArray([
                        'target' => 'gina',
                        'category' => 'dev',
                        'site_name' => 'Broken Profile',
                        'url' => 'https://profiles.test/users/gina',
                        'status' => 'Error',
                        'reason' => 'broken-profile: unexpected response body',
                        'mode' => 'username',
                        'key' => 'broken-profile',
                    ]),
                ],
            ),
            new MetadataRevalidationFakeValidator(
                key: 'inconclusive-profile',
                category: 'dev',
                mode: 'username',
                siteName: 'Inconclusive Profile',
                siteUrl: 'https://profiles.test/users/{user}',
                resultsByTarget: [
                    'frank' => ScanResult::fromArray([
                        'target' => 'frank',
                        'category' => 'dev',
                        'site_name' => 'Inconclusive Profile',
                        'url' => 'https://profiles.test/users/frank',
                        'status' => 'Error',
                        'reason' => 'cURL error 7: Failed to connect to profiles.test port 443 after 10 ms: Bad access',
                        'mode' => 'username',
                        'key' => 'inconclusive-profile',
                    ]),
                ],
            ),
        );

        $this->seedCapabilityInventory([
            [
                'mode' => 'username',
                'platform' => 'stable-profile',
                'category' => 'dev',
                'path' => 'user_scan/dev/stable_profile.py',
                'level' => 4,
                'strategy' => 'profile-html-enrichment',
                'notes' => 'Stable baseline',
                'validated_level' => 4,
                'validated_at' => '2026-07-01T00:00:00+00:00',
                'validated_targets' => ['alice'],
                'validated_notes' => 'Stable overlay',
            ],
            [
                'mode' => 'username',
                'platform' => 'partial-profile',
                'category' => 'dev',
                'path' => 'user_scan/dev/partial_profile.py',
                'level' => 4,
                'strategy' => 'profile-html-enrichment',
                'notes' => 'Partial baseline',
                'validated_level' => 4,
                'validated_at' => '2026-07-01T00:00:00+00:00',
                'validated_targets' => ['bob', 'charlie'],
                'validated_notes' => 'Partial overlay',
            ],
            [
                'mode' => 'username',
                'platform' => 'degraded-profile',
                'category' => 'dev',
                'path' => 'user_scan/dev/degraded_profile.py',
                'level' => 4,
                'strategy' => 'profile-html-enrichment',
                'notes' => 'Degraded baseline',
                'validated_level' => 4,
                'validated_at' => '2026-07-01T00:00:00+00:00',
                'validated_targets' => ['dana'],
                'validated_notes' => 'Degraded overlay',
            ],
            [
                'mode' => 'username',
                'platform' => 'blocked-profile',
                'category' => 'dev',
                'path' => 'user_scan/dev/blocked_profile.py',
                'level' => 4,
                'strategy' => 'profile-html-enrichment',
                'notes' => 'Blocked baseline',
                'validated_level' => 4,
                'validated_at' => '2026-07-01T00:00:00+00:00',
                'validated_targets' => ['erin'],
                'validated_notes' => 'Blocked overlay',
            ],
            [
                'mode' => 'username',
                'platform' => 'broken-profile',
                'category' => 'dev',
                'path' => 'user_scan/dev/broken_profile.py',
                'level' => 4,
                'strategy' => 'profile-html-enrichment',
                'notes' => 'Broken baseline',
                'validated_level' => 4,
                'validated_at' => '2026-07-01T00:00:00+00:00',
                'validated_targets' => ['gina'],
                'validated_notes' => 'Broken overlay',
            ],
            [
                'mode' => 'username',
                'platform' => 'inconclusive-profile',
                'category' => 'dev',
                'path' => 'user_scan/dev/inconclusive_profile.py',
                'level' => 4,
                'strategy' => 'profile-html-enrichment',
                'notes' => 'Inconclusive baseline',
                'validated_level' => 4,
                'validated_at' => '2026-07-01T00:00:00+00:00',
                'validated_targets' => ['frank'],
                'validated_notes' => 'Inconclusive overlay',
            ],
        ]);

        $this->app->forgetInstance(ScannerEngineService::class);
        $this->app->forgetInstance(MetadataAuditService::class);
        $this->app->forgetInstance(MetadataRevalidationService::class);
    }

    public function test_metadata_revalidation_command_writes_report_and_classifies_drift(): void
    {
        $reportPath = storage_path('framework/testing/metadata-revalidation-report.json');
        @unlink($reportPath);

        $this->artisan('scanner:metadata-revalidate', [
            'mode' => 'username',
            '--output' => $reportPath,
        ])
            ->expectsOutputToContain('Wrote metadata revalidation report')
            ->assertExitCode(0);

        $this->assertFileExists($reportPath);

        $report = json_decode((string) file_get_contents($reportPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('username', $report['mode']);
        $this->assertSame(6, $report['summary']['modules_requested']);
        $this->assertSame(1, $report['summary']['stable_modules']);
        $this->assertSame(1, $report['summary']['partial_modules']);
        $this->assertSame(1, $report['summary']['degraded_modules']);
        $this->assertSame(1, $report['summary']['blocked_modules']);
        $this->assertSame(1, $report['summary']['broken_modules']);
        $this->assertSame(1, $report['summary']['inconclusive_modules']);
        $this->assertSame(5, $report['summary']['unstable_modules']);
        $this->assertSame(3, $report['summary']['successful_targets']);
        $this->assertSame(4, $report['summary']['failed_targets']);

        $stable = collect($report['modules'])->firstWhere('module', 'stable-profile');
        $this->assertSame('stable', $stable['revalidation_status']);
        $this->assertSame(4, $stable['revalidated_level']);

        $partial = collect($report['modules'])->firstWhere('module', 'partial-profile');
        $this->assertSame('partial', $partial['revalidation_status']);
        $this->assertSame(['charlie'], $partial['failed_targets']);
        $this->assertSame(4, $partial['revalidated_level']);

        $degraded = collect($report['modules'])->firstWhere('module', 'degraded-profile');
        $this->assertSame('degraded', $degraded['revalidation_status']);
        $this->assertSame(2, $degraded['revalidated_level']);

        $blocked = collect($report['modules'])->firstWhere('module', 'blocked-profile');
        $this->assertSame('blocked', $blocked['revalidation_status']);
        $this->assertNull($blocked['revalidated_level']);

        $broken = collect($report['modules'])->firstWhere('module', 'broken-profile');
        $this->assertSame('broken', $broken['revalidation_status']);
        $this->assertNull($broken['revalidated_level']);

        $inconclusive = collect($report['modules'])->firstWhere('module', 'inconclusive-profile');
        $this->assertSame('inconclusive', $inconclusive['revalidation_status']);
        $this->assertNull($inconclusive['revalidated_level']);
    }

    public function test_metadata_revalidation_command_can_fail_when_drift_thresholds_are_exceeded(): void
    {
        $this->artisan('scanner:metadata-revalidate', [
            'mode' => 'username',
            '--max-degraded' => 0,
            '--max-blocked' => 0,
            '--max-inconclusive' => 0,
            '--max-broken' => 0,
            '--max-unstable' => 0,
        ])
            ->expectsOutputToContain('Unstable modules (5) exceeded the allowed maximum of 0.')
            ->expectsOutputToContain('Degraded modules (1) exceeded the allowed maximum of 0.')
            ->expectsOutputToContain('Blocked modules (1) exceeded the allowed maximum of 0.')
            ->expectsOutputToContain('Inconclusive modules (1) exceeded the allowed maximum of 0.')
            ->expectsOutputToContain('Broken modules (1) exceeded the allowed maximum of 0.')
            ->assertExitCode(1);
    }

    private function swapValidators(ValidatorContract ...$validators): void
    {
        $this->app->instance('scanner.validators', $validators);
        $this->app->forgetInstance(ScannerEngineService::class);
        $this->app->forgetInstance(MetadataAuditService::class);
        $this->app->forgetInstance(MetadataRevalidationService::class);
    }

    /**
     * @param array<int, array<string, mixed>> $inventory
     */
    private function seedCapabilityInventory(array $inventory): void
    {
        $service = new MetadataCapabilityService();
        $indexedInventory = [];
        foreach ($inventory as $record) {
            $indexedInventory[$record['mode'] . ':' . $record['platform']] = $record;
        }

        $reflection = new \ReflectionProperty(MetadataCapabilityService::class, 'inventory');
        $reflection->setAccessible(true);
        $reflection->setValue($service, $indexedInventory);

        $this->app->instance(MetadataCapabilityService::class, $service);
    }
}

final class MetadataRevalidationFakeValidator implements ValidatorContract
{
    /**
     * @param array<string, ScanResult> $resultsByTarget
     */
    public function __construct(
        private readonly string $key,
        private readonly string $category,
        private readonly string $mode,
        private readonly string $siteName,
        private readonly string $siteUrl,
        private readonly array $resultsByTarget,
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
        return $this->resultsByTarget[$target] ?? ScanResult::fromArray([
            'target' => $target,
            'category' => $this->category,
            'site_name' => $this->siteName,
            'url' => str_replace('{user}', $target, $this->siteUrl),
            'status' => 'Error',
            'reason' => $this->key . ': test target missing',
            'mode' => $this->mode,
            'key' => $this->key,
        ]);
    }
}
