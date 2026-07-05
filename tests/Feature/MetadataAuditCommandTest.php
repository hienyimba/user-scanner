<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use App\Services\Scanner\MetadataCapabilityService;
use App\Services\Scanner\MetadataAuditService;
use App\Services\Scanner\ScannerEngineService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class MetadataAuditCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->swapValidators(
            new MetadataAuditFakeValidator(
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
                    'extra' => "Name: Alice Example\nFollowers: 1.2k\nJoined: Jan 2, 2024",
                    'mode' => 'username',
                    'key' => 'rich-profile',
                ]),
            ),
            new MetadataAuditFakeValidator(
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
    }

    public function test_metadata_audit_command_writes_normalized_json_report(): void
    {
        Http::fake([
            'https://profiles.test/users/alice' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <meta property="og:title" content="Alice Example">
                    <meta property="og:description" content="Builder and researcher">
                    <meta property="og:image" content="https://cdn.test/alice.jpg">
                    <meta property="og:url" content="https://profiles.test/users/alice">
                    <script type="application/ld+json">
                        {"@context":"https://schema.org","@type":"Person","name":"Alice Example","sameAs":["https://social.test/alice"]}
                    </script>
                </head>
                <body>
                    <a href="https://portfolio.test">Portfolio</a>
                    <a href="mailto:alice@example.com">Email</a>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $outputPath = storage_path('framework/testing/metadata-audit-report.json');
        if (is_file($outputPath)) {
            unlink($outputPath);
        }

        $this->artisan('scanner:metadata-audit', [
            'mode' => 'username',
            'targets' => ['alice'],
            '--module' => ['rich-profile', 'blocked-profile'],
            '--output' => $outputPath,
            '--proxy' => 'http://secret-user:secret-pass@proxy.test:8000',
        ])
            ->expectsOutputToContain('Wrote metadata audit report')
            ->assertExitCode(0);

        $this->assertFileExists($outputPath);

        $report = json_decode((string) file_get_contents($outputPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('username', $report['mode']);
        $this->assertSame(['alice'], $report['targets']);
        $this->assertTrue($report['options']['proxy_supplied']);
        $this->assertTrue($report['options']['use_proxy']);
        $this->assertArrayNotHasKey('proxy', $report['options']);
        $this->assertSame(2, $report['summary']['audited_results']);
        $this->assertSame(1, $report['summary']['found_results']);
        $this->assertSame(1, $report['summary']['error_results']);
        $this->assertSame(1, $report['summary']['results_with_metadata_level_4']);
        $this->assertSame(1, $report['summary']['observed_level_counts']['level_0']);
        $this->assertSame(1, $report['summary']['observed_level_counts']['level_4']);
        $this->assertSame(1, $report['summary']['status_detail_counts']['anti_bot']);
        $this->assertSame(1, $report['summary']['status_detail_counts']['found']);

        $richRecord = collect($report['results'])->firstWhere('module', 'rich-profile');
        $this->assertNotNull($richRecord);
        $this->assertSame('found', $richRecord['normalized_status']);
        $this->assertSame(4, $richRecord['observed_metadata_level']);
        $this->assertContains('profile_url', $richRecord['evidence']);
        $this->assertContains('followers', $richRecord['metadata_keys']);
        $this->assertSame('2024-01-02T00:00:00+00:00', $richRecord['metadata']['created_at']);

        $blockedRecord = collect($report['results'])->firstWhere('module', 'blocked-profile');
        $this->assertNotNull($blockedRecord);
        $this->assertSame('error', $blockedRecord['normalized_status']);
        $this->assertSame('anti_bot', $blockedRecord['status_detail']);
        $this->assertSame(0, $blockedRecord['observed_metadata_level']);

        $packagistCapability = app(MetadataCapabilityService::class)->forModule('username', 'packagist');
        $this->assertNotNull($packagistCapability);
        $this->assertSame(3, $packagistCapability['validated_level']);
        $this->assertSame(['fabpot'], $packagistCapability['validated_targets']);
    }

    public function test_metadata_audit_command_can_fail_threshold_assertions_for_ci_gating(): void
    {
        Http::fake([
            'https://profiles.test/users/alice' => Http::response('<html><meta property="og:title" content="Alice Example"></html>', 200),
        ]);

        $this->artisan('scanner:metadata-audit', [
            'mode' => 'username',
            'targets' => ['alice'],
            '--module' => ['rich-profile'],
            '--min-level4' => 2,
        ])
            ->expectsOutputToContain('Observed metadata level 4 results')
            ->assertExitCode(1);
    }

    public function test_metadata_audit_command_resolves_configured_proxy_credentials_for_explicit_proxy_overrides(): void
    {
        config()->set('scanner.proxies.credentials.username', 'acct');
        config()->set('scanner.proxies.credentials.password', 's3cr3t');
        config()->set('scanner.proxies.pool', [
            [
                'entry_point' => 'proxy.test',
                'port' => 8000,
                'tier' => 'fallback',
                'enabled' => true,
            ],
        ]);

        $this->swapValidators(
            new MetadataAuditProxyEchoValidator(),
        );

        $reportPath = storage_path('framework/testing/metadata-audit-proxy-resolution.json');
        @unlink($reportPath);

        $this->artisan('scanner:metadata-audit', [
            'mode' => 'username',
            'targets' => ['alice'],
            '--module' => ['proxy-echo'],
            '--output' => $reportPath,
            '--proxy' => 'proxy.test:8000',
        ])->assertExitCode(0);

        $report = json_decode((string) file_get_contents($reportPath), true, 512, JSON_THROW_ON_ERROR);
        $record = collect($report['results'])->firstWhere('module', 'proxy-echo');

        $this->assertNotNull($record);
        $this->assertSame('http://acct:s3cr3t@proxy.test:8000', $record['metadata']['proxy_seen']);
    }

    public function test_metadata_audit_command_can_force_direct_requests_even_when_default_proxy_pool_exists(): void
    {
        config()->set('scanner.proxy_list', ['disp.oxylabs.io:8008']);

        $this->swapValidators(
            new MetadataAuditProxyEchoValidator(),
        );

        $reportPath = storage_path('framework/testing/metadata-audit-no-proxy.json');
        @unlink($reportPath);

        $this->artisan('scanner:metadata-audit', [
            'mode' => 'username',
            'targets' => ['alice'],
            '--module' => ['proxy-echo'],
            '--output' => $reportPath,
            '--no-proxy' => true,
        ])->assertExitCode(0);

        $report = json_decode((string) file_get_contents($reportPath), true, 512, JSON_THROW_ON_ERROR);
        $record = collect($report['results'])->firstWhere('module', 'proxy-echo');

        $this->assertNotNull($record);
        $this->assertFalse($report['options']['use_proxy']);
        $this->assertTrue($report['options']['disable_proxy']);
        $this->assertFalse($report['options']['proxy_supplied']);
        $this->assertArrayNotHasKey('proxy_seen', $record['metadata']);
    }

    private function swapValidators(ValidatorContract ...$validators): void
    {
        $this->app->instance('scanner.validators', $validators);
        $this->app->forgetInstance(ScannerEngineService::class);
        $this->app->forgetInstance(MetadataAuditService::class);
    }
}

final class MetadataAuditFakeValidator implements ValidatorContract
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

final class MetadataAuditProxyEchoValidator implements ValidatorContract
{
    public function key(): string
    {
        return 'proxy-echo';
    }

    public function category(): string
    {
        return 'dev';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Proxy Echo';
    }

    public function siteUrl(): string
    {
        return 'https://proxy.test/users/{user}';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        return ScanResult::fromArray([
            'target' => $target,
            'category' => 'dev',
            'site_name' => 'Proxy Echo',
            'url' => 'https://proxy.test',
            'status' => 'Taken',
            'mode' => 'username',
            'key' => 'proxy-echo',
            'metadata' => [
                'username' => $target,
                'proxy_seen' => $options['proxy'] ?? null,
            ],
        ]);
    }
}
