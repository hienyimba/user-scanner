<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\ScanResult;
use Tests\TestCase;

final class ScanResultNormalizationTest extends TestCase
{
    public function test_to_array_normalizes_minimal_found_result_without_enrichment(): void
    {
        $scanResult = ScanResult::fromArray([
            'target' => 'alice',
            'category' => 'social',
            'site_name' => 'Alpha',
            'url' => 'https://alpha.test/users/alice',
            'status' => 'Taken',
            'mode' => 'username',
            'key' => 'alpha',
            'metadata' => [
                'display_name' => 'Alice Example',
                'sources' => ['api_json'],
            ],
            'external_links' => ['https://portfolio.test'],
        ]);
        $result = $scanResult->toArray();

        $this->assertSame('found', $result['normalized_status']);
        $this->assertSame('https://alpha.test/users/alice', $result['profile_url']);
        $this->assertGreaterThan(0.8, (float) $result['confidence']);
        $this->assertSame('Alice Example', $result['metadata']['display_name']);
        $this->assertSame('alice', $result['metadata']['username']);
        $this->assertSame(['https://portfolio.test'], $result['metadata']['external_links']);
        $this->assertSame('found', $result['metadata']['status_detail']);
        $this->assertSame(3, $result['metadata']['observed_metadata_level']);
        $this->assertContains('profile_url', $result['normalized']['evidence']);
        $this->assertContains('display_name', $result['normalized']['evidence']);
        $this->assertSame('found', $result['normalized']['status']);
        $this->assertSame('alpha', $result['normalized']['platform']);
        $this->assertSame($result['normalized'], $scanResult->toNormalizedArray());
    }

    public function test_to_array_normalizes_minimal_error_result_without_enrichment(): void
    {
        $result = ScanResult::fromArray([
            'target' => 'alice',
            'category' => 'social',
            'site_name' => 'Beta',
            'url' => 'https://beta.test',
            'status' => 'Error',
            'reason' => 'beta: anti-bot challenge detected',
            'mode' => 'username',
            'key' => 'beta',
        ])->toArray();

        $this->assertSame('error', $result['normalized_status']);
        $this->assertNull($result['profile_url']);
        $this->assertSame(0.0, (float) $result['confidence']);
        $this->assertSame('anti_bot', $result['metadata']['status_detail']);
        $this->assertSame(0, $result['metadata']['observed_metadata_level']);
        $this->assertSame('beta', $result['metadata']['platform']);
        $this->assertSame('beta: anti-bot challenge detected', $result['error']);
        $this->assertSame('anti_bot', $result['normalized']['status_detail']);
        $this->assertSame('beta: anti-bot challenge detected', $result['normalized']['error']);
    }

    public function test_to_array_drops_explicit_profile_url_for_non_found_results(): void
    {
        $result = ScanResult::fromArray([
            'target' => 'alice',
            'category' => 'social',
            'site_name' => 'Beta',
            'url' => 'https://beta.test/users/alice',
            'status' => 'Error',
            'reason' => 'beta: blocked/rate-limited (HTTP 403)',
            'mode' => 'username',
            'key' => 'beta',
            'profile_url' => 'https://beta.test/users/alice',
        ])->toArray();

        $this->assertNull($result['profile_url']);
        $this->assertEmpty($result['normalized']['evidence']);
    }

    public function test_to_array_ignores_api_style_profile_url_and_falls_back_to_public_result_url(): void
    {
        $result = ScanResult::fromArray([
            'target' => 'alice',
            'category' => 'social',
            'site_name' => 'Alpha',
            'url' => 'https://alpha.test/users/alice',
            'status' => 'Taken',
            'mode' => 'username',
            'key' => 'alpha',
            'profile_url' => 'https://alpha.test/api/users?username=alice',
        ])->toArray();

        $this->assertSame('https://alpha.test/users/alice', $result['profile_url']);
        $this->assertContains('profile_url', $result['normalized']['evidence']);
    }

    public function test_to_array_sanitizes_invalid_public_metadata_values(): void
    {
        $result = ScanResult::fromArray([
            'target' => 'alice',
            'category' => 'social',
            'site_name' => 'Alpha',
            'url' => 'https://alpha.test/users/alice',
            'status' => 'Taken',
            'mode' => 'username',
            'key' => 'alpha',
            'metadata' => [
                'display_name' => 'Alice Example',
                'avatar_url' => 'javascript:alert(1)',
                'website_url' => '/relative-home',
                'public_email' => 'not-an-email',
                'external_links' => [
                    'mailto:alice@example.com',
                    'javascript:alert(1)',
                    'https://portfolio.test/alice#about',
                    '//social.test/alice',
                ],
            ],
        ])->toArray();

        $this->assertNull($result['metadata']['avatar_url']);
        $this->assertNull($result['metadata']['website_url']);
        $this->assertNull($result['metadata']['public_email']);
        $this->assertSame([
            'https://portfolio.test/alice',
            'https://social.test/alice',
        ], $result['metadata']['external_links']);
        $this->assertNotContains('avatar_url', $result['normalized']['evidence']);
        $this->assertNotContains('website_url', $result['normalized']['evidence']);
        $this->assertNotContains('public_email', $result['normalized']['evidence']);
    }

    public function test_to_array_does_not_treat_queried_email_as_extracted_public_email(): void
    {
        $result = ScanResult::fromArray([
            'target' => 'alice@example.com',
            'category' => 'dev',
            'site_name' => 'Mailbox',
            'url' => 'https://mailbox.test',
            'status' => 'Registered',
            'mode' => 'email',
            'key' => 'mailbox',
        ])->toArray();

        $this->assertSame('found', $result['normalized_status']);
        $this->assertNull($result['metadata']['public_email']);
        $this->assertSame('found', $result['metadata']['status_detail']);
        $this->assertSame(1, $result['metadata']['observed_metadata_level']);
        $this->assertEmpty($result['normalized']['evidence']);
        $this->assertSame(0.74, $result['confidence']);
    }

    public function test_to_array_normalizes_minimal_not_found_result_without_enrichment(): void
    {
        $result = ScanResult::fromArray([
            'target' => 'alice',
            'category' => 'social',
            'site_name' => 'Alpha',
            'url' => 'https://alpha.test/users/alice',
            'status' => 'Available',
            'mode' => 'username',
            'key' => 'alpha',
        ])->toArray();

        $this->assertSame('not_found', $result['normalized_status']);
        $this->assertNull($result['profile_url']);
        $this->assertSame(0.95, $result['confidence']);
        $this->assertSame('alice', $result['metadata']['username']);
        $this->assertSame('not_found', $result['metadata']['status_detail']);
        $this->assertSame(1, $result['metadata']['observed_metadata_level']);
        $this->assertEmpty($result['normalized']['evidence']);
        $this->assertSame('not_found', $result['normalized']['status']);
        $this->assertNull($result['normalized']['error']);
    }
}
