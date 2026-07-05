<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use App\Services\Scanner\ScannerEngineService;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class MetadataEnrichmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->swapValidators(
            new MetadataFakeValidator(
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
                    'extra' => "Name: Legacy Alice\nFollowers: 1.2k\nJoined: Jan 2, 2024",
                    'mode' => 'username',
                    'key' => 'rich-profile',
                ]),
            ),
            new MetadataFakeValidator(
                key: 'email-account',
                category: 'dev',
                mode: 'email',
                siteName: 'Email Account',
                siteUrl: 'https://accounts.test',
                result: ScanResult::fromArray([
                    'target' => 'alice@example.com',
                    'category' => 'dev',
                    'site_name' => 'Email Account',
                    'url' => 'https://accounts.test',
                    'status' => 'Registered',
                    'extra' => "Accounts matched: 2\nProviders: google, password",
                    'mode' => 'email',
                    'key' => 'email-account',
                ]),
            ),
            new MetadataFakeValidator(
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
            new MetadataFakeValidator(
                key: 'available-profile',
                category: 'social',
                mode: 'username',
                siteName: 'Available Profile',
                siteUrl: 'https://available.test/users/{user}',
                result: ScanResult::fromArray([
                    'target' => 'alice',
                    'category' => 'social',
                    'site_name' => 'Available Profile',
                    'url' => 'https://available.test/users/alice',
                    'status' => 'Available',
                    'mode' => 'username',
                    'key' => 'available-profile',
                ]),
            ),
        );
    }

    public function test_username_results_are_enriched_into_normalized_metadata_shape(): void
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
                        {"@context":"https://schema.org","@type":"Person","name":"Alice Example","description":"Builder and researcher","url":"https://portfolio.test","sameAs":["https://social.test/alice"]}
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

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'rich-profile',
            target: 'alice',
            options: ['enrich_metadata' => true],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('found', $result->normalizedStatus);
        $this->assertSame('rich-profile', $result->platform);
        $this->assertSame('https://profiles.test/users/alice', $result->profileUrl);
        $this->assertSame('Legacy Alice', $result->metadata['display_name']);
        $this->assertSame(1200, $result->metadata['followers']);
        $this->assertSame('2024-01-02T00:00:00+00:00', $result->metadata['created_at']);
        $this->assertSame('Builder and researcher', $result->metadata['bio']);
        $this->assertSame('https://cdn.test/alice.jpg', $result->metadata['avatar_url']);
        $this->assertSame('alice@example.com', $result->metadata['public_email']);
        $this->assertSame('Person', $result->metadata['account_type']);
        $this->assertContains('https://portfolio.test', $result->metadata['external_links']);
        $this->assertContains('https://social.test/alice', $result->metadata['external_links']);
        $this->assertSame('found', $result->metadata['status_detail']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
        $this->assertContains('legacy_extra', $result->metadata['sources']);
        $this->assertContains('opengraph', $result->metadata['sources']);
        $this->assertContains('jsonld', $result->metadata['sources']);
        $this->assertContains('profile_url', $result->metadata['evidence']);
        $this->assertContains('followers', $result->metadata['evidence']);
        $this->assertNotNull($result->confidence);
        $this->assertGreaterThan(0.8, (float) $result->confidence);
        $this->assertSame('found', $result->toArray()['normalized']['status']);
        $this->assertSame('found', $result->toArray()['normalized']['status_detail']);
        $this->assertSame('https://profiles.test/users/alice', $result->toArray()['normalized']['profile_url']);
        $this->assertSame(4, $result->toArray()['normalized']['metadata_level']);
        $this->assertContains('profile_url', $result->toArray()['normalized']['evidence']);
    }

    public function test_email_results_keep_legacy_extra_and_gain_normalized_metadata(): void
    {
        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'email-account',
            target: 'alice@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('found', $result->normalizedStatus);
        $this->assertNull($result->profileUrl);
        $this->assertSame("Accounts matched: 2\nProviders: google, password", $result->extra);
        $this->assertSame('2', (string) $result->metadata['accounts_matched']);
        $this->assertSame('google, password', $result->metadata['providers']);
        $this->assertSame('found', $result->metadata['status_detail']);
        $this->assertSame(3, $result->metadata['observed_metadata_level']);
        $this->assertContains('legacy_extra', $result->metadata['sources']);
    }

    public function test_error_results_gain_normalized_status_detail_without_changing_public_status(): void
    {
        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'blocked-profile',
            target: 'alice',
            options: [],
        );

        $this->assertSame('Error', $result->status);
        $this->assertSame('error', $result->normalizedStatus);
        $this->assertSame('anti_bot', $result->metadata['status_detail']);
        $this->assertSame(0, $result->metadata['observed_metadata_level']);
        $this->assertSame('anti_bot', $result->toArray()['normalized']['status_detail']);
    }

    public function test_network_failures_are_classified_as_network_errors(): void
    {
        $validator = new MetadataFakeValidator(
            key: 'network-profile',
            category: 'social',
            mode: 'username',
            siteName: 'Network Profile',
            siteUrl: 'https://network.test/users/{user}',
            result: ScanResult::fromArray([
                'target' => 'alice',
                'category' => 'social',
                'site_name' => 'Network Profile',
                'url' => 'https://network.test',
                'status' => 'Error',
                'reason' => 'cURL error 7: Failed to connect to network.test port 443 after 2 ms: Bad access',
                'mode' => 'username',
                'key' => 'network-profile',
            ]),
        );

        $this->swapValidators($validator);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'network-profile',
            target: 'alice',
            options: [],
        );

        $this->assertSame('network_error', $result->metadata['status_detail']);
        $this->assertSame('network_error', $result->toArray()['normalized']['status_detail']);
    }

    public function test_invalid_explicit_profile_url_falls_back_to_public_profile_before_html_fetch(): void
    {
        $this->swapValidators(
            new MetadataFakeValidator(
                key: 'fallback-profile',
                category: 'dev',
                mode: 'username',
                siteName: 'Fallback Profile',
                siteUrl: 'https://profiles.test/users/{user}',
                result: ScanResult::fromArray([
                    'target' => 'alice',
                    'category' => 'dev',
                    'site_name' => 'Fallback Profile',
                    'url' => 'https://profiles.test/api/users?username=alice',
                    'status' => 'Taken',
                    'mode' => 'username',
                    'key' => 'fallback-profile',
                    'profile_url' => 'https://profiles.test/api/users?username=alice',
                ]),
            ),
        );

        Http::fake([
            'https://profiles.test/users/alice' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <meta property="og:title" content="Alice Example">
                </head>
                <body></body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'fallback-profile',
            target: 'alice',
            options: ['enrich_metadata' => true],
        );

        $this->assertSame('https://profiles.test/users/alice', $result->profileUrl);
        $this->assertSame('Alice Example', $result->metadata['display_name']);
        Http::assertSentCount(1);
        Http::assertSent(static fn ($request) => $request->url() === 'https://profiles.test/users/alice');
    }

    public function test_enrichment_sanitizes_invalid_public_metadata_values_before_returning_result(): void
    {
        $this->swapValidators(
            new MetadataFakeValidator(
                key: 'sanitized-profile',
                category: 'dev',
                mode: 'username',
                siteName: 'Sanitized Profile',
                siteUrl: 'https://profiles.test/users/{user}',
                result: ScanResult::fromArray([
                    'target' => 'alice',
                    'category' => 'dev',
                    'site_name' => 'Sanitized Profile',
                    'url' => 'https://profiles.test/users/alice',
                    'status' => 'Taken',
                    'mode' => 'username',
                    'key' => 'sanitized-profile',
                    'metadata' => [
                        'display_name' => 'Alice Example',
                        'avatar_url' => 'data:image/png;base64,abc',
                        'website_url' => 'mailto:alice@example.com',
                        'public_email' => 'bad@@example..com',
                        'external_links' => [
                            'mailto:alice@example.com',
                            'https://portfolio.test/alice#bio',
                            '//social.test/alice',
                        ],
                    ],
                ]),
            ),
        );

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'sanitized-profile',
            target: 'alice',
            options: ['enrich_metadata' => false],
        );

        $this->assertNull($result->metadata['avatar_url']);
        $this->assertNull($result->metadata['website_url']);
        $this->assertNull($result->metadata['public_email']);
        $this->assertSame([
            'https://portfolio.test/alice',
            'https://social.test/alice',
        ], $result->metadata['external_links']);
        $this->assertContains('external_links', $result->metadata['evidence']);
        $this->assertNotContains('avatar_url', $result->metadata['evidence']);
        $this->assertNotContains('website_url', $result->metadata['evidence']);
        $this->assertNotContains('public_email', $result->metadata['evidence']);
    }

    public function test_not_found_results_keep_normalized_metadata_shape_without_profile_or_evidence(): void
    {
        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'available-profile',
            target: 'alice',
            options: [],
        );

        $this->assertSame('Not Found', $result->status);
        $this->assertSame('not_found', $result->normalizedStatus);
        $this->assertNull($result->profileUrl);
        $this->assertSame(0.95, $result->confidence);
        $this->assertSame('alice', $result->metadata['username']);
        $this->assertSame('not_found', $result->metadata['status_detail']);
        $this->assertSame(1, $result->metadata['observed_metadata_level']);
        $this->assertEmpty($result->metadata['evidence']);
        $this->assertEmpty($result->metadata['external_links']);
        $this->assertSame('not_found', $result->toArray()['normalized']['status']);
        $this->assertSame('not_found', $result->toArray()['normalized']['status_detail']);
        $this->assertNull($result->toArray()['normalized']['profile_url']);
        $this->assertSame(1, $result->toArray()['normalized']['metadata_level']);
    }

    public function test_primary_profile_html_metadata_is_preserved_without_second_fetch(): void
    {
        $this->swapValidators(new MetadataHtmlValidator());

        Http::fake([
            'https://profiles.test/users/alice' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <meta property="og:title" content="Alice Example">
                    <meta property="og:description" content="Builder and researcher">
                    <meta property="og:image" content="https://cdn.test/alice.jpg">
                    <link rel="canonical" href="https://portfolio.test/alice">
                    <script type="application/ld+json">
                        {"@context":"https://schema.org","@type":"Person","name":"Alice Example","sameAs":["https://social.test/alice"]}
                    </script>
                </head>
                <body>
                    <a href="https://portfolio.test/alice">Portfolio</a>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'html-profile',
            target: 'alice',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('found', $result->normalizedStatus);
        $this->assertSame('https://profiles.test/users/alice', $result->profileUrl);
        $this->assertSame('Alice Example', $result->metadata['display_name']);
        $this->assertSame('Builder and researcher', $result->metadata['bio']);
        $this->assertSame('https://cdn.test/alice.jpg', $result->metadata['avatar_url']);
        $this->assertSame('https://portfolio.test/alice', $result->metadata['website_url']);
        $this->assertContains('https://social.test/alice', $result->metadata['external_links']);
        $this->assertContains('opengraph', $result->metadata['sources']);
        $this->assertContains('jsonld', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
        Http::assertSentCount(1);
    }

    public function test_semantic_profile_html_without_opengraph_or_jsonld_still_produces_rich_metadata(): void
    {
        $this->swapValidators(
            new MetadataFakeValidator(
                key: 'semantic-profile',
                category: 'dev',
                mode: 'username',
                siteName: 'Semantic Profile',
                siteUrl: 'https://profiles.test/users/{user}',
                result: ScanResult::fromArray([
                    'target' => 'alice',
                    'category' => 'dev',
                    'site_name' => 'Semantic Profile',
                    'url' => 'https://profiles.test/users/alice',
                    'status' => 'Taken',
                    'mode' => 'username',
                    'key' => 'semantic-profile',
                ]),
            ),
        );

        Http::fake([
            'https://profiles.test/users/alice' => Http::response(
                <<<'HTML'
                <html>
                <body itemscope itemtype="https://schema.org/Person">
                    <h1 itemprop="name">Alice Example</h1>
                    <p itemprop="description">Builder and researcher</p>
                    <img itemprop="image" src="https://cdn.test/alice.jpg">
                    <a itemprop="url" href="https://portfolio.test/alice">Website</a>
                    <span itemprop="email">mailto:alice@example.com</span>
                    <span itemprop="homeLocation">Lagos</span>
                    <div>1.2k followers</div>
                    <div>Following 321</div>
                    <div>Posts: 42</div>
                    <div>Joined <time datetime="2024-01-02">Jan 2, 2024</time></div>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'semantic-profile',
            target: 'alice',
            options: ['enrich_metadata' => true],
        );

        $this->assertSame('Alice Example', $result->metadata['display_name']);
        $this->assertSame('Builder and researcher', $result->metadata['bio']);
        $this->assertSame('https://cdn.test/alice.jpg', $result->metadata['avatar_url']);
        $this->assertSame('https://portfolio.test/alice', $result->metadata['website_url']);
        $this->assertSame('alice@example.com', $result->metadata['public_email']);
        $this->assertSame('Lagos', $result->metadata['location']);
        $this->assertSame(1200, $result->metadata['followers']);
        $this->assertSame(321, $result->metadata['following']);
        $this->assertSame(42, $result->metadata['posts_count']);
        $this->assertSame('2024-01-02T00:00:00+00:00', $result->metadata['created_at']);
        $this->assertContains('semantic_html', $result->metadata['sources']);
        $this->assertContains('html_stats', $result->metadata['sources']);
        $this->assertContains('html_dates', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_hydration_profile_html_without_opengraph_or_jsonld_still_produces_rich_metadata(): void
    {
        $this->swapValidators(
            new MetadataFakeValidator(
                key: 'hydration-profile',
                category: 'dev',
                mode: 'username',
                siteName: 'Hydration Profile',
                siteUrl: 'https://profiles.test/users/{user}',
                result: ScanResult::fromArray([
                    'target' => 'kaifcodec',
                    'category' => 'dev',
                    'site_name' => 'Hydration Profile',
                    'url' => 'https://profiles.test/users/kaifcodec',
                    'status' => 'Taken',
                    'mode' => 'username',
                    'key' => 'hydration-profile',
                ]),
            ),
        );

        Http::fake([
            'https://profiles.test/users/kaifcodec' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <script id="__NEXT_DATA__" type="application/json">
                        {
                            "props": {
                                "pageProps": {
                                    "userProfile": {
                                        "displayName": "Kaif Codec",
                                        "bio": "Shipping visual systems.",
                                        "avatarUrl": "https://images.example/avatar.jpg",
                                        "websiteUrl": "https://kaif.dev",
                                        "location": "Lagos, Nigeria",
                                        "followersCount": 321,
                                        "followingCount": 18,
                                        "postsCount": 42,
                                        "verified": true,
                                        "createdAt": "2024-02-03T04:05:06Z",
                                        "sameAs": ["https://x.example/kaifcodec"],
                                        "__typename": "UserProfile"
                                    }
                                }
                            }
                        }
                    </script>
                </head>
                <body></body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'hydration-profile',
            target: 'kaifcodec',
            options: ['enrich_metadata' => true],
        );

        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('Shipping visual systems.', $result->metadata['bio']);
        $this->assertSame('https://images.example/avatar.jpg', $result->metadata['avatar_url']);
        $this->assertSame('https://kaif.dev', $result->metadata['website_url']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame(321, $result->metadata['followers']);
        $this->assertSame(18, $result->metadata['following']);
        $this->assertSame(42, $result->metadata['posts_count']);
        $this->assertTrue($result->metadata['is_verified']);
        $this->assertSame('2024-02-03T04:05:06+00:00', $result->metadata['created_at']);
        $this->assertContains('https://x.example/kaifcodec', $result->metadata['external_links']);
        $this->assertContains('html_hydration', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_data_attribute_profile_html_without_scripts_still_produces_rich_metadata(): void
    {
        $this->swapValidators(
            new MetadataFakeValidator(
                key: 'attribute-profile',
                category: 'music',
                mode: 'username',
                siteName: 'Attribute Profile',
                siteUrl: 'https://profiles.test/users/{user}',
                result: ScanResult::fromArray([
                    'target' => 'kaifcodec',
                    'category' => 'music',
                    'site_name' => 'Attribute Profile',
                    'url' => 'https://profiles.test/users/kaifcodec',
                    'status' => 'Taken',
                    'mode' => 'username',
                    'key' => 'attribute-profile',
                ]),
            ),
        );

        Http::fake([
            'https://profiles.test/users/kaifcodec' => Http::response(
                <<<'HTML'
                <html>
                <body>
                    <div data-store="{&quot;profile&quot;:{&quot;name&quot;:&quot;Kaif Codec&quot;,&quot;about&quot;:&quot;Shipping audio systems.&quot;,&quot;location&quot;:&quot;Lagos, Nigeria&quot;,&quot;website_url&quot;:&quot;https://kaif.dev&quot;,&quot;followers_count&quot;:321,&quot;following_count&quot;:18,&quot;track_count&quot;:9,&quot;avatar_url&quot;:&quot;https://images.example/attribute-avatar.jpg&quot;,&quot;socialLinks&quot;:[{&quot;url&quot;:&quot;https://social.test/kaifcodec&quot;},{&quot;href&quot;:&quot;https://github.com/kaifcodec&quot;}]}}"></div>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'attribute-profile',
            target: 'kaifcodec',
            options: ['enrich_metadata' => true],
        );

        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('Shipping audio systems.', $result->metadata['bio']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame('https://kaif.dev', $result->metadata['website_url']);
        $this->assertSame(321, $result->metadata['followers']);
        $this->assertSame(18, $result->metadata['following']);
        $this->assertSame(9, $result->metadata['posts_count']);
        $this->assertSame('https://images.example/attribute-avatar.jpg', $result->metadata['avatar_url']);
        $this->assertContains('https://social.test/kaifcodec', $result->metadata['external_links']);
        $this->assertContains('https://github.com/kaifcodec', $result->metadata['external_links']);
        $this->assertContains('html_data_attributes', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_var_assignment_profile_html_without_opengraph_or_jsonld_still_produces_rich_metadata(): void
    {
        $this->swapValidators(
            new MetadataFakeValidator(
                key: 'variable-profile',
                category: 'social',
                mode: 'username',
                siteName: 'Variable Profile',
                siteUrl: 'https://profiles.test/users/{user}',
                result: ScanResult::fromArray([
                    'target' => 'kaifcodec',
                    'category' => 'social',
                    'site_name' => 'Variable Profile',
                    'url' => 'https://profiles.test/users/kaifcodec',
                    'status' => 'Taken',
                    'mode' => 'username',
                    'key' => 'variable-profile',
                ]),
            ),
        );

        Http::fake([
            'https://profiles.test/users/kaifcodec' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <script>
                        var ytInitialData = {"metadata":{"channelMetadataRenderer":{"title":"Kaif Codec","description":"Systems and threat research.","externalId":"UC1234567890","vanityChannelUrl":"https://youtube.com/@kaifcodec","isFamilySafe":true,"avatar":{"thumbnails":[{"url":"https://images.example/youtube-avatar.jpg"}]}}}};
                    </script>
                </head>
                <body></body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'variable-profile',
            target: 'kaifcodec',
            options: ['enrich_metadata' => true],
        );

        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('Systems and threat research.', $result->metadata['bio']);
        $this->assertSame('https://images.example/youtube-avatar.jpg', $result->metadata['avatar_url']);
        $this->assertSame('https://youtube.com/@kaifcodec', $result->metadata['website_url']);
        $this->assertContains('https://youtube.com/@kaifcodec', $result->metadata['external_links']);
        $this->assertContains('html_hydration', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    private function swapValidators(ValidatorContract ...$validators): void
    {
        $this->app->instance('scanner.validators', $validators);
        $this->app->forgetInstance(ScannerEngineService::class);
    }
}

final class MetadataFakeValidator implements ValidatorContract
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

final class MetadataHtmlValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'html-profile';
    }

    public function category(): string
    {
        return 'social';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'HTML Profile';
    }

    public function siteUrl(): string
    {
        return 'https://profiles.test/users/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://profiles.test/users/{$target}";
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return $response->status() === 200
            ? ['Taken', '']
            : ['Available', ''];
    }
}
