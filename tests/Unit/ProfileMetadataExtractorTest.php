<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Scanner\ProfileMetadataExtractor;
use Tests\TestCase;

final class ProfileMetadataExtractorTest extends TestCase
{
    public function test_extract_profile_html_metadata_collects_opengraph_jsonld_and_public_links(): void
    {
        config()->set('scanner.metadata.max_external_links', 10);

        $extractor = app(ProfileMetadataExtractor::class);
        $metadata = $extractor->extractProfileHtmlMetadata(
            <<<'HTML'
            <html>
            <head>
                <meta property="og:title" content="Alice Example">
                <meta property="og:description" content="Builder and researcher">
                <meta property="og:image" content="https://cdn.test/alice.jpg">
                <meta property="og:url" content="https://profiles.test/users/alice">
                <script type="application/ld+json">
                    {
                        "@context":"https://schema.org",
                        "@type":"Person",
                        "name":"Alice Example",
                        "description":"Builder and researcher",
                        "email":"mailto:alice@example.com",
                        "sameAs":["https://social.test/alice"],
                        "homeLocation":{"name":"Lagos"}
                    }
                </script>
            </head>
            <body>
                <a href="https://portfolio.test">Portfolio</a>
                <a href="mailto:alice@example.com">Email</a>
                <a href="https://profiles.test/internal">Ignore same host</a>
            </body>
            </html>
            HTML,
            'https://profiles.test/users/alice',
        );

        $this->assertSame('Alice Example', $metadata['display_name']);
        $this->assertSame('Builder and researcher', $metadata['bio']);
        $this->assertSame('https://cdn.test/alice.jpg', $metadata['avatar_url']);
        $this->assertSame('alice@example.com', $metadata['public_email']);
        $this->assertSame('Lagos', $metadata['location']);
        $this->assertSame('Person', $metadata['account_type']);
        $this->assertContains('https://portfolio.test', $metadata['external_links']);
        $this->assertContains('https://social.test/alice', $metadata['external_links']);
        $this->assertNotContains('https://profiles.test/internal', $metadata['external_links']);
        $this->assertContains('opengraph', $metadata['sources']);
        $this->assertContains('jsonld', $metadata['sources']);
        $this->assertContains('mailto', $metadata['sources']);
        $this->assertContains('html_links', $metadata['sources']);
    }

    public function test_extract_metric_value_supports_suffixes_and_plain_numbers(): void
    {
        $extractor = app(ProfileMetadataExtractor::class);

        $this->assertSame(1200, $extractor->extractMetricValue('1.2k'));
        $this->assertSame(2000000, $extractor->extractMetricValue('2m'));
        $this->assertSame(42, $extractor->extractMetricValue('42'));
        $this->assertSame('n/a', $extractor->extractMetricValue('n/a'));
    }

    public function test_normalize_date_value_converts_parseable_dates_to_iso8601(): void
    {
        $extractor = app(ProfileMetadataExtractor::class);

        $this->assertSame('2024-01-02T00:00:00+00:00', $extractor->normalizeDateValue('Jan 2, 2024'));
        $this->assertSame('not-a-date', $extractor->normalizeDateValue('not-a-date'));
    }

    public function test_extract_profile_html_metadata_collects_semantic_html_stats_and_dates(): void
    {
        $extractor = app(ProfileMetadataExtractor::class);
        $metadata = $extractor->extractProfileHtmlMetadata(
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
                <div>Last active <time datetime="2024-03-04T10:11:12Z">Yesterday</time></div>
            </body>
            </html>
            HTML,
            'https://profiles.test/users/alice',
        );

        $this->assertSame('Alice Example', $metadata['display_name']);
        $this->assertSame('Builder and researcher', $metadata['bio']);
        $this->assertSame('https://cdn.test/alice.jpg', $metadata['avatar_url']);
        $this->assertSame('https://portfolio.test/alice', $metadata['website_url']);
        $this->assertSame('alice@example.com', $metadata['public_email']);
        $this->assertSame('Lagos', $metadata['location']);
        $this->assertSame('Person', $metadata['account_type']);
        $this->assertSame(1200, $metadata['followers']);
        $this->assertSame(321, $metadata['following']);
        $this->assertSame(42, $metadata['posts_count']);
        $this->assertSame('2024-01-02T00:00:00+00:00', $metadata['created_at']);
        $this->assertSame('2024-03-04T10:11:12+00:00', $metadata['last_active_at']);
        $this->assertContains('semantic_html', $metadata['sources']);
        $this->assertContains('html_stats', $metadata['sources']);
        $this->assertContains('html_dates', $metadata['sources']);
    }

    public function test_extract_profile_html_metadata_collects_next_data_hydration_metadata(): void
    {
        $extractor = app(ProfileMetadataExtractor::class);
        $metadata = $extractor->extractProfileHtmlMetadata(
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
            'https://profiles.test/users/kaifcodec',
        );

        $this->assertSame('Kaif Codec', $metadata['display_name']);
        $this->assertSame('Shipping visual systems.', $metadata['bio']);
        $this->assertSame('https://images.example/avatar.jpg', $metadata['avatar_url']);
        $this->assertSame('https://kaif.dev', $metadata['website_url']);
        $this->assertSame('Lagos, Nigeria', $metadata['location']);
        $this->assertSame(321, $metadata['followers']);
        $this->assertSame(18, $metadata['following']);
        $this->assertSame(42, $metadata['posts_count']);
        $this->assertTrue($metadata['is_verified']);
        $this->assertSame('2024-02-03T04:05:06+00:00', $metadata['created_at']);
        $this->assertSame('UserProfile', $metadata['account_type']);
        $this->assertContains('https://x.example/kaifcodec', $metadata['external_links']);
        $this->assertContains('html_hydration', $metadata['sources']);
    }

    public function test_extract_profile_html_metadata_collects_assignment_style_hydration_metadata(): void
    {
        $extractor = app(ProfileMetadataExtractor::class);
        $metadata = $extractor->extractProfileHtmlMetadata(
            <<<'HTML'
            <html>
            <head>
                <script>
                    window.__NUXT__={"data":[{"publicUser":{"full_name":"Kaif Codec","about":"Builder of WebVetted.","city":"Lagos","country_code":"NG","follower_count":1200,"following_count":44,"track_count":9,"verified":true,"avatar_url":"https://images.example/nuxt-avatar.jpg","website":"https://webvetted.com"}}]};
                </script>
            </head>
            <body></body>
            </html>
            HTML,
            'https://profiles.test/users/kaifcodec',
        );

        $this->assertSame('Kaif Codec', $metadata['display_name']);
        $this->assertSame('Builder of WebVetted.', $metadata['bio']);
        $this->assertSame('https://images.example/nuxt-avatar.jpg', $metadata['avatar_url']);
        $this->assertSame('https://webvetted.com', $metadata['website_url']);
        $this->assertSame('Lagos, NG', $metadata['location']);
        $this->assertSame(1200, $metadata['followers']);
        $this->assertSame(44, $metadata['following']);
        $this->assertSame(9, $metadata['posts_count']);
        $this->assertTrue($metadata['is_verified']);
        $this->assertContains('html_hydration', $metadata['sources']);
    }

    public function test_extract_profile_html_metadata_collects_data_attribute_hydration_metadata(): void
    {
        $extractor = app(ProfileMetadataExtractor::class);
        $metadata = $extractor->extractProfileHtmlMetadata(
            <<<'HTML'
            <html>
            <body>
                <div
                    data-blob="{&quot;fan_data&quot;:{&quot;name&quot;:&quot;Kaif Codec&quot;,&quot;bio&quot;:&quot;Collector of sounds.&quot;,&quot;location&quot;:&quot;Lagos, Nigeria&quot;,&quot;website_url&quot;:&quot;https://kaif.dev&quot;,&quot;followers_count&quot;:321,&quot;following_count&quot;:18,&quot;track_count&quot;:9,&quot;avatar_url&quot;:&quot;https://images.example/bandcamp-avatar.jpg&quot;,&quot;links&quot;:[{&quot;url&quot;:&quot;https://social.test/kaifcodec&quot;},{&quot;href&quot;:&quot;https://github.com/kaifcodec&quot;}]}}">
                </div>
            </body>
            </html>
            HTML,
            'https://profiles.test/users/kaifcodec',
        );

        $this->assertSame('Kaif Codec', $metadata['display_name']);
        $this->assertSame('Collector of sounds.', $metadata['bio']);
        $this->assertSame('Lagos, Nigeria', $metadata['location']);
        $this->assertSame('https://kaif.dev', $metadata['website_url']);
        $this->assertSame(321, $metadata['followers']);
        $this->assertSame(18, $metadata['following']);
        $this->assertSame(9, $metadata['posts_count']);
        $this->assertSame('https://images.example/bandcamp-avatar.jpg', $metadata['avatar_url']);
        $this->assertContains('https://social.test/kaifcodec', $metadata['external_links']);
        $this->assertContains('https://github.com/kaifcodec', $metadata['external_links']);
        $this->assertContains('html_data_attributes', $metadata['sources']);
    }

    public function test_extract_profile_html_metadata_collects_var_assignment_hydration_metadata(): void
    {
        $extractor = app(ProfileMetadataExtractor::class);
        $metadata = $extractor->extractProfileHtmlMetadata(
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
            'https://profiles.test/users/kaifcodec',
        );

        $this->assertSame('Kaif Codec', $metadata['display_name']);
        $this->assertSame('Systems and threat research.', $metadata['bio']);
        $this->assertSame('https://images.example/youtube-avatar.jpg', $metadata['avatar_url']);
        $this->assertSame('https://youtube.com/@kaifcodec', $metadata['website_url']);
        $this->assertContains('https://youtube.com/@kaifcodec', $metadata['external_links']);
        $this->assertContains('html_hydration', $metadata['sources']);
    }

    public function test_extract_profile_html_metadata_collects_dotted_assignment_hydration_metadata(): void
    {
        $extractor = app(ProfileMetadataExtractor::class);
        $metadata = $extractor->extractProfileHtmlMetadata(
            <<<'HTML'
            <html>
            <head>
                <script>
                    Site.journal = {"id":606,"display_username":"Kaif Codec","is_paid":true,"is_community":false};
                </script>
            </head>
            <body></body>
            </html>
            HTML,
            'https://profiles.test/users/kaifcodec',
        );

        $this->assertSame('Kaif Codec', $metadata['display_name']);
        $this->assertSame('user', $metadata['account_type']);
        $this->assertContains('html_hydration', $metadata['sources']);
    }

    public function test_extract_profile_html_metadata_collects_rich_public_pivot_hydration_fields(): void
    {
        $extractor = app(ProfileMetadataExtractor::class);
        $metadata = $extractor->extractProfileHtmlMetadata(
            <<<'HTML'
            <html>
            <head>
                <script id="__NEXT_DATA__" type="application/json">
                    {
                        "props": {
                            "pageProps": {
                                "profile": {
                                    "userId": 4242,
                                    "username": "kaifcodec",
                                    "displayName": "Kaif Codec",
                                    "gender": "male",
                                    "isPrivate": true,
                                    "isPremium": true,
                                    "showProBadge": true,
                                    "verified_type": "official",
                                    "jid": "kaifcodec@smule.example",
                                    "friends_count": 88,
                                    "tips_count": 12,
                                    "playlist_count": 6,
                                    "stations_count": 4,
                                    "countries": ["Nigeria", "Ghana"],
                                    "social_links": ["https://x.example/kaifcodec"],
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
            'https://profiles.test/users/kaifcodec',
        );

        $this->assertSame('kaifcodec', $metadata['username']);
        $this->assertSame(4242, $metadata['user_id']);
        $this->assertSame('Kaif Codec', $metadata['display_name']);
        $this->assertSame('male', $metadata['gender']);
        $this->assertSame('official', $metadata['verified_type']);
        $this->assertSame('kaifcodec@smule.example', $metadata['jid']);
        $this->assertTrue($metadata['is_private']);
        $this->assertTrue($metadata['is_premium']);
        $this->assertTrue($metadata['show_pro_badge']);
        $this->assertSame(88, $metadata['friends']);
        $this->assertSame(12, $metadata['tips']);
        $this->assertSame(6, $metadata['playlist_count']);
        $this->assertSame(4, $metadata['stations']);
        $this->assertSame(['Nigeria', 'Ghana'], $metadata['countries']);
        $this->assertContains('https://x.example/kaifcodec', $metadata['external_links']);
        $this->assertContains('html_hydration', $metadata['sources']);
    }
}
