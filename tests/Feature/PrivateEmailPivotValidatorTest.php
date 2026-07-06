<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Scanner\ScannerEngineService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PrivateEmailPivotValidatorTest extends TestCase
{
    public function test_gitlab_email_validator_enriches_from_public_gravatar_link(): void
    {
        Http::fake([
            'https://en.gravatar.com/*.json' => Http::response([
                'entry' => [[
                    'accounts' => [
                        ['url' => 'https://gitlab.com/kaifcodec'],
                    ],
                ]],
            ], 200),
            'https://gitlab.com/api/v4/users*' => Http::response([
                [
                    'id' => 4242,
                    'username' => 'kaifcodec',
                    'name' => 'Kaif Codec',
                    'state' => 'active',
                    'avatar_url' => 'https://secure.gravatar.com/avatar/abcdef1234567890abcdef1234567890?s=80&d=identicon',
                ],
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'gitlab',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('https://gitlab.com/kaifcodec', $result->profileUrl);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame(4242, $result->metadata['gitlab_id']);
        $this->assertSame(4242, $result->metadata['user_id']);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('active', $result->metadata['account_state']);
        $this->assertContains('gitlab_public_api', $result->metadata['sources']);
        $this->assertSame(0.97, $result->confidence);
    }

    public function test_foursquare_email_validator_enriches_from_public_gravatar_link(): void
    {
        $this->fakePivotProfile(
            validatorKey: 'foursquare',
            profileUrl: 'https://foursquare.com/kaifcodec',
            profileHtml: <<<'HTML'
                <html>
                    <head>
                        <title>Kaif Codec - Foursquare</title>
                        <meta property="og:title" content="Kaif Codec - Foursquare" />
                        <meta property="og:description" content="Explorer and mapper." />
                        <meta property="og:image" content="https://images.example/foursquare-avatar.jpg" />
                    </head>
                </html>
            HTML,
        );

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'foursquare',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('https://foursquare.com/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec - Foursquare', $result->metadata['display_name']);
        $this->assertSame('Explorer and mapper.', $result->metadata['bio']);
        $this->assertSame('https://images.example/foursquare-avatar.jpg', $result->metadata['avatar_url']);
        $this->assertContains('profile_html', $result->metadata['sources']);
    }

    public function test_foursquare_email_validator_collects_richer_public_hydration_metadata(): void
    {
        $this->fakePivotProfile(
            validatorKey: 'foursquare',
            profileUrl: 'https://foursquare.com/kaifcodec',
            profileHtml: <<<'HTML'
                <html>
                    <head>
                        <meta property="og:title" content="Kaif Codec - Foursquare" />
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
                                            "tips_count": 12,
                                            "friends_count": 88,
                                            "posts_count": 17,
                                            "social_links": ["https://x.example/kaifcodec"],
                                            "__typename": "UserProfile"
                                        }
                                    }
                                }
                            }
                        </script>
                    </head>
                </html>
            HTML,
        );

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'foursquare',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame(4242, $result->metadata['user_id']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('male', $result->metadata['gender']);
        $this->assertTrue((bool) $result->metadata['is_private']);
        $this->assertSame(12, $result->metadata['tips']);
        $this->assertSame(88, $result->metadata['friends']);
        $this->assertSame(17, $result->metadata['posts_count']);
        $this->assertContains('https://x.example/kaifcodec', $result->metadata['external_links']);
    }

    public function test_vsco_email_validator_enriches_from_public_gravatar_link(): void
    {
        $this->fakePivotProfile(
            validatorKey: 'vsco',
            profileUrl: 'https://vsco.co/kaifcodec',
            profileHtml: <<<'HTML'
                <html>
                    <head>
                        <meta property="og:title" content="Kaif Codec" />
                        <meta property="og:description" content="Photographer." />
                        <meta property="og:image" content="https://images.example/vsco-avatar.jpg" />
                    </head>
                </html>
            HTML,
        );

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'vsco',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('https://vsco.co/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('https://images.example/vsco-avatar.jpg', $result->metadata['avatar_url']);
    }

    public function test_smule_email_validator_enriches_from_public_gravatar_link(): void
    {
        $this->fakePivotProfile(
            validatorKey: 'smule',
            profileUrl: 'https://www.smule.com/kaifcodec',
            profileHtml: <<<'HTML'
                <html>
                    <head>
                        <meta property="og:title" content="kaifcodec" />
                        <meta property="og:description" content="Singer and creator." />
                        <meta property="og:image" content="https://images.example/smule-avatar.jpg" />
                    </head>
                </html>
            HTML,
        );

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'smule',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('https://www.smule.com/kaifcodec', $result->profileUrl);
        $this->assertSame('kaifcodec', $result->metadata['display_name']);
        $this->assertSame('https://images.example/smule-avatar.jpg', $result->metadata['avatar_url']);
    }

    public function test_smule_email_validator_collects_richer_public_hydration_metadata(): void
    {
        $this->fakePivotProfile(
            validatorKey: 'smule',
            profileUrl: 'https://www.smule.com/kaifcodec',
            profileHtml: <<<'HTML'
                <html>
                    <head>
                        <script>
                            window.__NUXT__={"data":[{"publicUser":{"userId":777,"username":"kaifcodec","displayName":"Kaif Codec","jid":"kaifcodec@smule.example","verified_type":"official","avatar_url":"https://images.example/smule-avatar.jpg","followers_count":321,"following_count":18,"track_count":9}}]};
                        </script>
                    </head>
                </html>
            HTML,
        );

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'smule',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame(777, $result->metadata['user_id']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('kaifcodec@smule.example', $result->metadata['jid']);
        $this->assertSame('official', $result->metadata['verified_type']);
        $this->assertSame(321, $result->metadata['followers']);
        $this->assertSame(18, $result->metadata['following']);
        $this->assertSame(9, $result->metadata['posts_count']);
        $this->assertSame('https://images.example/smule-avatar.jpg', $result->metadata['avatar_url']);
        $this->assertContains('html_hydration', $result->metadata['sources']);
    }

    public function test_bible_email_validator_enriches_from_public_gravatar_link(): void
    {
        $this->fakePivotProfile(
            validatorKey: 'bible',
            profileUrl: 'https://www.bible.com/users/kaifcodec',
            profileHtml: <<<'HTML'
                <html>
                    <head>
                        <meta property="og:title" content="Kaif Codec" />
                        <meta property="og:description" content="Reading plans and notes." />
                    </head>
                </html>
            HTML,
        );

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'bible',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('https://www.bible.com/users/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('Reading plans and notes.', $result->metadata['bio']);
    }

    public function test_bible_email_validator_collects_richer_public_hydration_metadata(): void
    {
        $this->fakePivotProfile(
            validatorKey: 'bible',
            profileUrl: 'https://www.bible.com/users/kaifcodec',
            profileHtml: <<<'HTML'
                <html>
                    <head>
                        <script id="__NEXT_DATA__" type="application/json">
                            {
                                "props": {
                                    "pageProps": {
                                        "profile": {
                                            "userId": 123456,
                                            "username": "kaifcodec",
                                            "displayName": "Kaif Codec",
                                            "avatarUrl": "https://images.example/bible-avatar.jpg",
                                            "__typename": "UserProfile"
                                        }
                                    }
                                }
                            }
                        </script>
                    </head>
                </html>
            HTML,
        );

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'bible',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame(123456, $result->metadata['user_id']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('https://images.example/bible-avatar.jpg', $result->metadata['avatar_url']);
        $this->assertContains('html_hydration', $result->metadata['sources']);
    }

    public function test_wanderlog_email_validator_enriches_from_public_gravatar_link(): void
    {
        $this->fakePivotProfile(
            validatorKey: 'wanderlog',
            profileUrl: 'https://wanderlog.com/u/kaifcodec',
            profileHtml: <<<'HTML'
                <html>
                    <head>
                        <meta property="og:title" content="Kaif Codec" />
                        <meta property="og:description" content="Travel itineraries." />
                        <meta property="og:image" content="https://images.example/wanderlog-avatar.jpg" />
                    </head>
                </html>
            HTML,
        );

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'wanderlog',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('https://wanderlog.com/u/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('https://images.example/wanderlog-avatar.jpg', $result->metadata['avatar_url']);
    }

    public function test_wanderlog_email_validator_collects_richer_public_hydration_metadata(): void
    {
        $this->fakePivotProfile(
            validatorKey: 'wanderlog',
            profileUrl: 'https://wanderlog.com/u/kaifcodec',
            profileHtml: <<<'HTML'
                <html>
                    <head>
                        <script>
                            window.__NUXT__={"data":[{"tripUser":{"userId":9001,"username":"kaifcodec","displayName":"Kaif Codec","post_count":5,"countries":["Nigeria","Ghana"],"is_premium":true,"show_pro_badge":true,"avatar_url":"https://images.example/wanderlog-avatar.jpg"}}]};
                        </script>
                    </head>
                </html>
            HTML,
        );

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'wanderlog',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame(9001, $result->metadata['user_id']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame(5, $result->metadata['posts_count']);
        $this->assertSame(['Nigeria', 'Ghana'], $result->metadata['countries']);
        $this->assertTrue((bool) $result->metadata['is_premium']);
        $this->assertTrue((bool) $result->metadata['show_pro_badge']);
        $this->assertSame('https://images.example/wanderlog-avatar.jpg', $result->metadata['avatar_url']);
    }

    public function test_plex_email_validator_enriches_from_public_gravatar_link(): void
    {
        $this->fakePivotProfile(
            validatorKey: 'plex',
            profileUrl: 'https://forums.plex.tv/u/kaifcodec',
            profileHtml: <<<'HTML'
                <html>
                    <head>
                        <meta property="og:title" content="Kaif Codec" />
                        <meta property="og:description" content="Plex community profile." />
                        <meta property="og:image" content="https://images.example/plex-avatar.jpg" />
                    </head>
                </html>
            HTML,
        );

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'plex',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('https://forums.plex.tv/u/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('https://images.example/plex-avatar.jpg', $result->metadata['avatar_url']);
    }

    public function test_libravatar_email_validator_returns_public_avatar_evidence(): void
    {
        Http::fake([
            'https://seccdn.libravatar.org/avatar/*' => Http::response('png-bytes', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'libravatar',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('libravatar', $result->metadata['source']);
        $this->assertStringContainsString('https://seccdn.libravatar.org/avatar/', $result->metadata['avatar_url']);
        $this->assertArrayHasKey('hash_md5', $result->metadata);
        $this->assertArrayHasKey('hash_sha256', $result->metadata);
    }

    public function test_libravatar_email_validator_reports_not_registered_when_no_avatar_exists(): void
    {
        Http::fake([
            'https://seccdn.libravatar.org/avatar/*' => Http::response('', 404),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'libravatar',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Not Registered', $result->status);
    }

    public function test_unavatar_email_validator_returns_public_avatar_evidence(): void
    {
        Http::fake([
            'https://unavatar.io/email/*' => Http::response([
                'url' => 'https://avatars.githubusercontent.com/u/4242?v=4',
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'unavatar',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('github', $result->metadata['source']);
        $this->assertSame('https://avatars.githubusercontent.com/u/4242?v=4', $result->metadata['avatar_url']);
        $this->assertArrayHasKey('hash_md5', $result->metadata);
        $this->assertArrayHasKey('hash_sha256', $result->metadata);
    }

    public function test_unavatar_email_validator_reports_not_registered_when_no_avatar_exists(): void
    {
        Http::fake([
            'https://unavatar.io/email/*' => Http::response('', 404),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'unavatar',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Not Registered', $result->status);
    }

    public function test_public_pivot_validator_skips_without_matching_public_evidence(): void
    {
        Http::fake([
            'https://en.gravatar.com/*.json' => Http::response([
                'entry' => [[
                    'accounts' => [
                        ['url' => 'https://github.com/kaifcodec'],
                    ],
                ]],
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'foursquare',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Skipped', $result->status);
        $this->assertStringContainsString('No public Gravatar evidence', $result->reason);
    }

    #[DataProvider('skippedValidatorProvider')]
    public function test_safety_blocked_email_validators_return_explicit_structured_skipped_status(
        string $validatorKey,
        array $expectedBlockedFields,
        array $expectedSensitiveFields,
    ): void
    {
        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: $validatorKey,
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Skipped', $result->status);
        $this->assertNotSame('', $result->reason);
        $this->assertSame(0.0, $result->confidence);
        $this->assertSame('safety_blocked', $result->metadata['status_detail']);
        $this->assertTrue((bool) $result->metadata['safety_blocked']);
        $this->assertFalse((bool) $result->metadata['supported']);
        $this->assertSame('safety_blocked_placeholder', $result->metadata['metadata_strategy']);
        $this->assertSame(0, $result->metadata['observed_metadata_level']);
        $this->assertSame($expectedBlockedFields, $result->metadata['blocked_metadata_fields']);
        $this->assertSame($expectedSensitiveFields, $result->metadata['sensitive_fields']);
        $this->assertContains('safety_blocked', $result->metadata['evidence']);
        $this->assertContains('metadata_strategy', $result->metadata['evidence']);
        $this->assertSame([], $result->metadata['sources']);
    }

    /**
     * @return array<int, array{0:string, 1:array<int, string>, 2:array<int, string>}>
     */
    public static function skippedValidatorProvider(): array
    {
        return [
            ['gmail', [], []],
            ['pandora', ['username', 'display_name', 'user_id', 'avatar_url', 'followers', 'following', 'posts_count', 'is_private', 'is_premium', 'playlist_count', 'stations'], []],
            ['typeform', ['display_name', 'is_verified', 'has_password', 'is_sso', 'needs_password_reset'], ['has_password', 'is_sso', 'needs_password_reset']],
            ['dropbox', ['has_passkey'], ['has_passkey']],
            ['doordash', ['phones', 'social_channels'], ['phones', 'social_channels']],
            ['ripe', ['phones'], ['phones']],
        ];
    }

    private function fakePivotProfile(string $validatorKey, string $profileUrl, string $profileHtml): void
    {
        Http::fake([
            'https://en.gravatar.com/*.json' => Http::response([
                'entry' => [[
                    'accounts' => [
                        ['url' => $profileUrl],
                    ],
                ]],
            ], 200),
            $profileUrl => Http::response($profileHtml, 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]),
        ]);
    }
}
