<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use App\Services\Scanner\QueuedScanService;
use App\Services\Scanner\ScannerEngineService;
use Tests\TestCase;

final class ScanApiNormalizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('scanner.auto_skip.username', []);

        $this->swapValidators(
            new ScanApiFakeValidator(
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
                    'extra' => "Name: Alice Example\nFollowers: 42",
                    'mode' => 'username',
                    'key' => 'rich-profile',
                    'metadata' => [
                        'display_name' => 'Alice Example',
                        'bio' => 'Builder and researcher',
                        'followers' => 42,
                        'sources' => ['api_json'],
                    ],
                ]),
            ),
            new ScanApiFakeValidator(
                key: 'blocked-profile',
                category: 'social',
                mode: 'username',
                siteName: 'Blocked Profile',
                siteUrl: 'https://blocked.test/users/{user}',
                result: ScanResult::fromArray([
                    'target' => 'alice',
                    'category' => 'social',
                    'site_name' => 'Blocked Profile',
                    'url' => 'https://blocked.test',
                    'status' => 'Error',
                    'reason' => 'blocked-profile: anti-bot challenge detected',
                    'mode' => 'username',
                    'key' => 'blocked-profile',
                ]),
            ),
            new ScanApiFakeValidator(
                key: 'skip-me',
                category: 'social',
                mode: 'username',
                siteName: 'Skip Me',
                siteUrl: 'https://skip.test/users/{user}',
                result: ScanResult::fromArray([
                    'target' => 'alice',
                    'category' => 'social',
                    'site_name' => 'Skip Me',
                    'url' => 'https://skip.test',
                    'status' => 'Taken',
                    'mode' => 'username',
                    'key' => 'skip-me',
                ]),
            ),
            new ScanApiFakeValidator(
                key: 'missing-profile',
                category: 'social',
                mode: 'username',
                siteName: 'Missing Profile',
                siteUrl: 'https://missing.test/users/{user}',
                result: ScanResult::fromArray([
                    'target' => 'alice',
                    'category' => 'social',
                    'site_name' => 'Missing Profile',
                    'url' => 'https://missing.test/users/alice',
                    'status' => 'Available',
                    'mode' => 'username',
                    'key' => 'missing-profile',
                ]),
            ),
        );
    }

    public function test_internal_scan_api_returns_normalized_found_payload_shape(): void
    {
        $response = $this->postJson('/api/scanner/run', [
            'mode' => 'username',
            'target' => 'alice',
            'module_keys' => ['rich-profile'],
        ]);

        $response->assertOk()->assertJson([
            'ok' => true,
            'meta' => [
                'mode' => 'username',
                'target' => 'alice',
                'count' => 1,
            ],
        ]);

        $result = $response->json('results.0');

        $this->assertSame('Found', $result['status']);
        $this->assertSame('found', $result['normalized_status']);
        $this->assertSame('https://profiles.test/users/alice', $result['profile_url']);
        $this->assertSame('rich-profile', $result['platform']);
        $this->assertGreaterThan(0.8, (float) $result['confidence']);
        $this->assertSame('Alice Example', $result['metadata']['display_name']);
        $this->assertSame('Builder and researcher', $result['metadata']['bio']);
        $this->assertSame('alice', $result['metadata']['username']);
        $this->assertSame(42, $result['metadata']['followers']);
        $this->assertNull($result['metadata']['location']);
        $this->assertNull($result['metadata']['website_url']);
        $this->assertSame('found', $result['metadata']['status_detail']);
        $this->assertSame(4, $result['metadata']['observed_metadata_level']);
        $this->assertContains('profile_url', $result['metadata']['evidence']);
        $this->assertSame('found', $result['normalized']['status']);
        $this->assertSame('username', $result['normalized']['mode']);
        $this->assertSame('rich-profile', $result['normalized']['platform']);
        $this->assertSame('https://profiles.test', $result['normalized']['url']);
        $this->assertSame('https://profiles.test/users/alice', $result['normalized']['profile_url']);
        $this->assertSame(4, $result['normalized']['metadata_level']);
        $this->assertNull($result['normalized']['error']);
    }

    public function test_internal_scan_api_returns_normalized_error_and_skipped_payloads(): void
    {
        config()->set('scanner.auto_skip.username', ['skip-me' => 'Temporarily auto-skipped']);

        $response = $this->postJson('/api/scanner/run', [
            'mode' => 'username',
            'target' => 'alice',
            'module_keys' => ['blocked-profile', 'skip-me'],
        ]);

        $response->assertOk();
        $results = collect($response->json('results'))->keyBy('key');

        $blocked = $results['blocked-profile'];
        $this->assertSame('Error', $blocked['status']);
        $this->assertSame('error', $blocked['normalized_status']);
        $this->assertSame('anti_bot', $blocked['metadata']['status_detail']);
        $this->assertSame(0, $blocked['metadata']['observed_metadata_level']);
        $this->assertSame('blocked-profile: anti-bot challenge detected', $blocked['error']);
        $this->assertSame('blocked-profile: anti-bot challenge detected', $blocked['normalized']['error']);

        $skipped = $results['skip-me'];
        $this->assertSame('Skipped', $skipped['status']);
        $this->assertSame('skipped', $skipped['normalized_status']);
        $this->assertSame('Temporarily auto-skipped', $skipped['reason']);
        $this->assertSame('skipped', $skipped['metadata']['status_detail']);
        $this->assertSame(0, $skipped['metadata']['observed_metadata_level']);
        $this->assertSame(0.0, (float) $skipped['confidence']);
        $this->assertNull($skipped['profile_url']);
        $this->assertNull($skipped['error']);
        $this->assertSame('skipped', $skipped['normalized']['status']);
        $this->assertNull($skipped['normalized']['error']);
    }

    public function test_internal_scan_api_returns_normalized_not_found_payload_shape(): void
    {
        $response = $this->postJson('/api/scanner/run', [
            'mode' => 'username',
            'target' => 'alice',
            'module_keys' => ['missing-profile'],
        ]);

        $response->assertOk();
        $result = $response->json('results.0');

        $this->assertSame('Not Found', $result['status']);
        $this->assertSame('not_found', $result['normalized_status']);
        $this->assertNull($result['profile_url']);
        $this->assertSame(0.95, $result['confidence']);
        $this->assertSame('alice', $result['metadata']['username']);
        $this->assertSame('not_found', $result['metadata']['status_detail']);
        $this->assertSame(1, $result['metadata']['observed_metadata_level']);
        $this->assertEmpty($result['metadata']['evidence']);
        $this->assertSame('not_found', $result['normalized']['status']);
        $this->assertSame('not_found', $result['normalized']['status_detail']);
        $this->assertSame(1, $result['normalized']['metadata_level']);
        $this->assertNull($result['normalized']['error']);
    }

    private function swapValidators(ValidatorContract ...$validators): void
    {
        $this->app->instance('scanner.validators', $validators);
        $this->app->forgetInstance(ScannerEngineService::class);
        $this->app->forgetInstance(QueuedScanService::class);
    }
}

final class ScanApiFakeValidator implements ValidatorContract
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
