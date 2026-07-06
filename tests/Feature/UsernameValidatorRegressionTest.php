<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Scanner\Validators\Generated\User\AcademiaValidator;
use App\Services\Scanner\Validators\Generated\User\AmazonValidator;
use App\Services\Scanner\Validators\Generated\User\ArduinoValidator;
use App\Services\Scanner\Validators\Generated\User\AppledeveloperValidator;
use App\Services\Scanner\Validators\Generated\User\AirlinersValidator;
use App\Services\Scanner\Validators\Generated\User\AtcoderValidator;
use App\Services\Scanner\Validators\Generated\User\AsciinemaValidator;
use App\Services\Scanner\Validators\Generated\User\CodecademyValidator;
use App\Services\Scanner\Validators\Generated\User\CodeforcesValidator;
use App\Services\Scanner\Validators\Generated\User\DailyDevValidator;
use App\Services\Scanner\Validators\Generated\User\DuolingoValidator;
use App\Services\Scanner\Validators\Generated\User\DiscourseMetaValidator;
use App\Services\Scanner\Validators\Generated\User\ElixirForumValidator;
use App\Services\Scanner\Validators\Generated\User\FanslyValidator;
use App\Services\Scanner\Validators\Generated\User\FDroidValidator;
use App\Services\Scanner\Validators\Generated\User\FiverrValidator;
use App\Services\Scanner\Validators\Generated\User\FreelancerValidator;
use App\Services\Scanner\Validators\Generated\User\HashnodeValidator;
use App\Services\Scanner\Validators\Generated\User\InstructablesValidator;
use App\Services\Scanner\Validators\Generated\User\JupyterForumValidator;
use App\Services\Scanner\Validators\Generated\User\KickValidator;
use App\Services\Scanner\Validators\Generated\User\KaggleValidator;
use App\Services\Scanner\Validators\Generated\User\LaunchpadValidator;
use App\Services\Scanner\Validators\Generated\User\LivejournalValidator;
use App\Services\Scanner\Validators\Generated\User\NiftygatewayValidator;
use App\Services\Scanner\Validators\Generated\User\PackagistValidator;
use App\Services\Scanner\Validators\Generated\User\PastebinValidator;
use App\Services\Scanner\Validators\Generated\User\Px500Validator;
use App\Services\Scanner\Validators\Generated\User\ProtonmailValidator;
use App\Services\Scanner\Validators\Generated\User\PypiValidator;
use App\Services\Scanner\Validators\Generated\User\SubstackValidator;
use App\Services\Scanner\Validators\Generated\User\RubygemsValidator;
use App\Services\Scanner\Validators\Generated\User\Site35photoValidator;
use App\Services\Scanner\Validators\Generated\User\TumblrValidator;
use App\Services\Scanner\Validators\Generated\User\WikipediaValidator;
use App\Services\Scanner\ScannerEngineService;
use App\Services\Scanner\Validators\Generated\User\HackernewsValidator;
use App\Services\Scanner\Validators\Generated\Manual\User\GithubValidator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class UsernameValidatorRegressionTest extends TestCase
{
    public function test_get_query_parameters_embedded_in_request_url_are_preserved(): void
    {
        Http::fake(function (Request $request) {
            parse_str($request->toPsrRequest()->getUri()->getQuery(), $query);

            $this->assertSame('hienyimba', $query['ususers'] ?? null);
            $this->assertSame('json', $query['format'] ?? null);
            $this->assertSame('2', $query['formatversion'] ?? null);

            return Http::response([
                'query' => [
                    'users' => [
                        ['userid' => 29054392, 'name' => 'Hienyimba'],
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']);
        });

        $result = (new WikipediaValidator())->check('hienyimba');

        $this->assertSame('Taken', $result->status);
    }

    public function test_public_profile_url_heuristic_preserves_query_and_subdomain_profiles(): void
    {
        $this->assertSame(
            'https://news.ycombinator.com/user?id=pg',
            (new HackernewsValidator())->publicProfileUrl('pg')
        );
        $this->assertSame(
            'https://astralcodexten.substack.com',
            (new SubstackValidator())->publicProfileUrl('astralcodexten')
        );
    }

    #[DataProvider('generatedStatusValidatorProvider')]
    public function test_generated_status_validators_handle_basic_taken_and_available_responses(string $className, int $status, string $expected): void
    {
        Http::fake(['*' => Http::response('<html></html>', $status)]);

        $result = (new $className())->check('hienyimba');

        $this->assertSame($expected, $result->status);
    }

    #[DataProvider('manualAvailabilityProvider')]
    public function test_manual_username_validators_treat_empty_or_not_found_payloads_as_available(string $className, int $status, array|string $payload): void
    {
        $response = is_array($payload)
            ? Http::response($payload, $status, ['Content-Type' => 'application/json'])
            : Http::response($payload, $status);

        Http::fake(['*' => $response]);

        $result = (new $className())->check('hienyimba');

        $this->assertSame('Available', $result->status);
    }

    public function test_pypi_xmlrpc_empty_result_is_available_even_with_whitespace(): void
    {
        Http::fake(function (Request $request) {
            $this->assertSame('POST', $request->method());
            $this->assertSame('https://pypi.org/pypi', (string) $request->url());

            return Http::response("<?xml version='1.0'?><methodResponse><params><param><value><array><data>\n</data></array></value></param></params></methodResponse>", 200);
        });

        $result = (new PypiValidator())->check('hienyimba');

        $this->assertSame('Available', $result->status);
    }

    public function test_atcoder_true_response_is_taken_even_with_whitespace(): void
    {
        Http::fake(['*' => Http::response(" \ntrue\t ", 200)]);

        $result = (new AtcoderValidator())->check('chokudai');

        $this->assertSame('Taken', $result->status);
    }

    public function test_atcoder_false_response_is_available_even_with_whitespace(): void
    {
        Http::fake(['*' => Http::response(" \nfalse\t ", 200)]);

        $result = (new AtcoderValidator())->check('missing-user');

        $this->assertSame('Available', $result->status);
    }

    public function test_instructables_404_page_is_available_even_if_html_mentions_challenge_scripts(): void
    {
        Http::fake([
            '*' => Http::response('<html><head><title>Page Not Found - Instructables</title></head><body><script src="/cdn-cgi/challenge-platform/scripts/jsd/main.js"></script></body></html>', 404, ['Content-Type' => 'text/html']),
        ]);

        $result = (new InstructablesValidator())->check('hienyimba');

        $this->assertSame('Available', $result->status);
    }

    public function test_tumblr_404_page_is_available_even_if_html_contains_challenge_like_text(): void
    {
        Http::fake([
            '*' => Http::response('<!DOCTYPE html><html><head><title>Not found.</title></head><body data-status-code="404">challenge-platform placeholder</body></html>', 404, ['Content-Type' => 'text/html']),
        ]);

        $result = (new TumblrValidator())->check('hienyimba');

        $this->assertSame('Available', $result->status);
    }

    public function test_codecademy_next_data_user_not_found_is_available(): void
    {
        Http::fake([
            '*' => Http::response(
                '<html><script src="/cdn-cgi/challenge-platform/scripts/jsd/main.js"></script><script id="__NEXT_DATA__" type="application/json">{"props":{"pageProps":{"profile":{"__typename":"UserNotFound","type":"UserNotFound","message":"missing"}}}}</script></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = (new CodecademyValidator())->check('hienyimba');

        $this->assertSame('Available', $result->status);
    }

    public function test_packagist_200_profile_page_is_taken(): void
    {
        Http::fake([
            '*' => Http::response('<html><head><title>hienyimba - Packagist</title></head><body></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = (new PackagistValidator())->check('hienyimba');

        $this->assertSame('Taken', $result->status);
    }

    public function test_daily_dev_next_data_user_payload_is_taken(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <body>
                    <script id="__NEXT_DATA__" type="application/json">{"props":{"pageProps":{"user":{"id":"usr_123","name":"Kaif Codec"}}}}</script>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = (new DailyDevValidator())->check('kaifcodec');

        $this->assertSame('Taken', $result->status);
    }

    public function test_daily_dev_noindex_payload_is_available(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <body>
                    <script id="__NEXT_DATA__" type="application/json">{"props":{"pageProps":{"noindex":true}}}</script>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = (new DailyDevValidator())->check('missing-user');

        $this->assertSame('Available', $result->status);
    }

    public function test_protonmail_taken_code_is_reported_as_taken(): void
    {
        Http::fake(function (Request $request) {
            $this->assertSame('https://account.proton.me/api/core/v4/users/available?Name=kaifcodec%40proton.me&ParseDomain=1', (string) $request->url());
            $this->assertSame('web-mail@6.0.1.3', $request->header('x-pm-appversion')[0] ?? null);

            return Http::response(['Code' => 12106], 409, ['Content-Type' => 'application/json']);
        });

        $result = (new ProtonmailValidator())->check('kaifcodec');

        $this->assertSame('Taken', $result->status);
    }

    public function test_protonmail_available_code_is_reported_as_available(): void
    {
        Http::fake([
            '*' => Http::response(['Code' => 1000], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = (new ProtonmailValidator())->check('missing-user');

        $this->assertSame('Available', $result->status);
    }

    public function test_pastebin_profile_markers_are_taken(): void
    {
        Http::fake([
            '*' => Http::response('<html><div class="info-bar"></div><div class="user-icon"><img src="/i/guest.png" /></div></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = (new PastebinValidator())->check('kaifcodec');

        $this->assertSame('Taken', $result->status);
    }

    public function test_pastebin_plain_200_without_profile_markers_is_available(): void
    {
        Http::fake([
            '*' => Http::response('<html><body>Anonymous pastes only.</body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = (new PastebinValidator())->check('missing-user');

        $this->assertSame('Available', $result->status);
    }

    public function test_airliners_pow_challenge_is_solved_and_replayed(): void
    {
        $requestCount = 0;

        Http::fake(function (Request $request) use (&$requestCount) {
            $requestCount++;

            if ($requestCount === 1) {
                return Http::response(
                    "<html><body>challenge_nonce:'nonce123' challenge_hmac:'hmac456' difficulty:'1' difficulty_char:'0' issued_at:'issued789'</body></html>",
                    202,
                    ['Content-Type' => 'text/html']
                );
            }

            $cookieHeader = $request->header('Cookie')[0] ?? '';
            $this->assertStringContainsString('pow_bypass=nonce123|issued789|', $cookieHeader);
            $parts = explode('|', substr($cookieHeader, strlen('pow_bypass=')));
            $this->assertCount(5, $parts);
            $this->assertSame('nonce123', $parts[0]);
            $this->assertSame('issued789', $parts[1]);
            $this->assertSame('hmac456', $parts[4]);
            $this->assertTrue(str_starts_with($parts[3], '0'));

            return Http::response('<html><body>profile</body></html>', 200, ['Content-Type' => 'text/html']);
        });

        $result = (new AirlinersValidator())->check('kaifcodec');

        $this->assertSame('Taken', $result->status);
        $this->assertSame(2, $requestCount);
    }

    public function test_daily_dev_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <body>
                    <script id="__NEXT_DATA__" type="application/json">{"props":{"pageProps":{"user":{"id":"usr_123","name":"Kaif Codec"}}}}</script>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'daily_dev',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://app.daily.dev/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('usr_123', $result->metadata['daily_dev_user_id']);
        $this->assertContains('html_hydration', $result->metadata['sources']);
    }

    public function test_pastebin_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <body>
                    <div class="info-bar"></div>
                    <div class="user-icon"><img src="/images/avatars/kaifcodec.png" /></div>
                    <span class="views">1,234</span>
                    <span class="views -all">6,789</span>
                    <span class="rating">9.7</span>
                    <span class="date-text" title="2022-01-02T03:04:05+00:00"></span>
                    <a class="web" href="https://kaif.dev"></a>
                    <span class="location">Lagos, Nigeria</span>
                    <div class="pro"></div>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'pastebin',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://pastebin.com/u/kaifcodec', $result->profileUrl);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('https://pastebin.com/images/avatars/kaifcodec.png', $result->metadata['avatar_url']);
        $this->assertSame('https://kaif.dev', $result->metadata['website_url']);
        $this->assertContains('https://kaif.dev', $result->metadata['external_links']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame(1234, $result->metadata['views']);
        $this->assertSame(6789, $result->metadata['all_views']);
        $this->assertSame('9.7', $result->metadata['rating']);
        $this->assertSame('2022-01-02T03:04:05+00:00', $result->metadata['created_at']);
        $this->assertTrue((bool) $result->metadata['is_pro']);
        $this->assertContains('profile_html', $result->metadata['sources']);
    }

    public function test_substack_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <title>Kaif Codec | Substack</title>
                    <script type="application/ld+json">{"@context":"https://schema.org","@type":"Person","name":"Kaif Codec","description":"Essays on systems and OSINT.","url":"https://kaifcodec.substack.com","sameAs":["https://x.com/kaifcodec"]}</script>
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
            validatorKey: 'substack',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://kaifcodec.substack.com', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Essays on systems and OSINT.', $result->metadata['bio']);
        $this->assertSame('Person', $result->metadata['account_type']);
        $this->assertContains('https://x.com/kaifcodec', $result->metadata['external_links']);
        $this->assertContains('jsonld', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_discourse_meta_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'user' => [
                    'id' => 512,
                    'name' => 'Kaif Codec',
                    'username' => 'kaifcodec',
                    'title' => 'Core contributor',
                    'created_at' => '2021-02-03T04:05:06Z',
                    'last_seen_at' => '2024-05-06T07:08:09Z',
                    'avatar_template' => '/user_avatar/meta.discourse.org/kaifcodec/{size}/1_2.png',
                    'bio_cooked' => '<p><a href="https://kaif.dev">Website</a></p>',
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'discourse_meta',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://meta.discourse.org/u/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('https://meta.discourse.org/user_avatar/meta.discourse.org/kaifcodec/512/1_2.png', $result->metadata['avatar_url']);
        $this->assertSame('Core contributor', $result->metadata['account_type']);
        $this->assertSame('2021-02-03T04:05:06+00:00', $result->metadata['created_at']);
        $this->assertSame('2024-05-06T07:08:09+00:00', $result->metadata['last_active_at']);
        $this->assertContains('https://kaif.dev', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    #[DataProvider('discourseStyleEmptyUserProvider')]
    public function test_discourse_style_validators_follow_june_empty_user_parity(string $className, string $expectedStatus, string $expectedReason): void
    {
        Http::fake([
            '*' => Http::response(['user' => []], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = (new $className())->check('hienyimba');

        $this->assertSame($expectedStatus, $result->status);
        $this->assertSame($expectedReason, $result->reason);
    }

    public function test_foursquare_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <title>Kaif Codec - Foursquare</title>
                    <meta property="og:description" content="Discovering great places in Lagos." />
                    <meta property="og:image" content="https://images.example/foursquare-avatar.jpg" />
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
            validatorKey: 'foursquare',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://foursquare.com/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Kaif Codec - Foursquare', $result->metadata['profile_title']);
        $this->assertSame('Discovering great places in Lagos.', $result->metadata['bio']);
        $this->assertSame('https://images.example/foursquare-avatar.jpg', $result->metadata['avatar_url']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_weebly_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <title>Kaif Codec Studio</title>
                    <meta name="description" content="Design notes and experiments." />
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
            validatorKey: 'weebly',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://kaifcodec.weebly.com/', $result->profileUrl);
        $this->assertSame('Kaif Codec Studio', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Kaif Codec Studio', $result->metadata['profile_title']);
        $this->assertSame('Design notes and experiments.', $result->metadata['bio']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_instructables_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <title>Kaif Codec - Instructables</title>
                    <meta name="description" content="Maker projects and teardown notes." />
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
            validatorKey: 'instructables',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.instructables.com/member/kaifcodec/', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Kaif Codec - Instructables', $result->metadata['profile_title']);
        $this->assertSame('Maker projects and teardown notes.', $result->metadata['bio']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_wikipedia_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'query' => [
                    'users' => [
                        [
                            'userid' => 29054392,
                            'name' => 'Kaifcodec',
                            'editcount' => 128,
                            'registration' => '2022-01-02T03:04:05Z',
                            'gender' => 'male',
                        ],
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'wikipedia',
            target: 'Kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://en.wikipedia.org/wiki/User:Kaifcodec', $result->profileUrl);
        $this->assertSame('Kaifcodec', $result->metadata['display_name']);
        $this->assertSame('Kaifcodec', $result->metadata['username']);
        $this->assertSame(29054392, $result->metadata['wikipedia_user_id']);
        $this->assertSame(128, $result->metadata['edit_count']);
        $this->assertSame(128, $result->metadata['posts_count']);
        $this->assertSame('male', $result->metadata['gender']);
        $this->assertSame('2022-01-02T03:04:05+00:00', $result->metadata['created_at']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_ameblo_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <title>Kaif Codec プロフィール</title>
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
            validatorKey: 'ameblo',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://ameblo.jp/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Kaif Codec プロフィール', $result->metadata['profile_title']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(3, $result->metadata['observed_metadata_level']);
    }

    public function test_operaforums_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'joindateISO' => '2020-01-02T03:04:05Z',
                'reputation' => 275,
                'profileviews' => 931,
                'location' => 'Lagos, Nigeria',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'operaforums',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://forums.opera.com/user/kaifcodec', $result->profileUrl);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame('2020-01-02T03:04:05+00:00', $result->metadata['created_at']);
        $this->assertSame(275, $result->metadata['reputation']);
        $this->assertSame(931, $result->metadata['profile_views']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_youtube_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <meta property="og:title" content="Kaif Codec" />
                    <meta property="og:description" content="Systems and threat research." />
                </head>
                <body>
                    <script>var ytInitialData = {"metadata":{"channelMetadataRenderer":{"title":"Kaif Codec","description":"Systems and threat research.","externalId":"UC1234567890","vanityChannelUrl":"https://youtube.com/@kaifcodec","isFamilySafe":true,"keywords":"osint, security","avatar":{"thumbnails":[{"url":"https://images.example/youtube-avatar.jpg"}]}}}};</script>
                    <script>{"content":"1.23M subscribers"}</script>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'youtube',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://youtube.com/@kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Systems and threat research.', $result->metadata['bio']);
        $this->assertSame('UC1234567890', $result->metadata['youtube_channel_id']);
        $this->assertSame('https://youtube.com/@kaifcodec', $result->metadata['channel_url']);
        $this->assertTrue((bool) $result->metadata['is_family_safe']);
        $this->assertSame('osint, security', $result->metadata['keywords']);
        $this->assertSame('https://images.example/youtube-avatar.jpg', $result->metadata['avatar_url']);
        $this->assertSame(1230000, $result->metadata['followers']);
        $this->assertContains('https://youtube.com/@kaifcodec', $result->metadata['external_links']);
        $this->assertContains('html_hydration', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_bandlab_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 77441,
                'name' => 'Kaif Codec',
                'about' => 'Building music-native intelligence tooling.',
                'place' => 'Lagos, Nigeria',
                'createdOn' => '2020-04-03T12:34:56Z',
                'picture' => [
                    'url' => 'https://images.example/bandlab-avatar.jpg',
                ],
                'counters' => [
                    'followers' => 4200,
                    'following' => 310,
                    'plays' => 985000,
                    'bands' => 12,
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'bandlab',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.bandlab.com/kaifcodec', $result->profileUrl);
        $this->assertSame(77441, $result->metadata['bandlab_id']);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Building music-native intelligence tooling.', $result->metadata['bio']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame('2020-04-03T12:34:56+00:00', $result->metadata['created_at']);
        $this->assertSame('https://images.example/bandlab-avatar.jpg', $result->metadata['avatar_url']);
        $this->assertSame(4200, $result->metadata['followers']);
        $this->assertSame(310, $result->metadata['following']);
        $this->assertSame(985000, $result->metadata['plays_count']);
        $this->assertSame(12, $result->metadata['bands_count']);
        $this->assertSame(12, $result->metadata['posts_count']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_telegram_profile_page_with_extra_block_is_taken_and_extracts_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <meta property="og:title" content="Kaif Codec" />
                    <meta property="og:description" content="Security notes and alerts." />
                    <meta property="og:image" content="https://images.example/telegram-avatar.jpg" />
                </head>
                <body>
                    <div class="tgme_page_extra">1.2K subscribers</div>
                    <a href="https://kaif.dev">Portfolio</a>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'telegram',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://t.me/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Security notes and alerts.', $result->metadata['bio']);
        $this->assertSame('https://images.example/telegram-avatar.jpg', $result->metadata['avatar_url']);
        $this->assertContains('https://kaif.dev', $result->metadata['external_links']);
        $this->assertContains('opengraph', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_telegram_profile_page_without_extra_block_is_available(): void
    {
        Http::fake([
            '*' => Http::response('<html><head><title>Telegram: Contact @ghost</title></head><body><div>Profile not found</div></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = (new \App\Services\Scanner\Validators\Generated\User\TelegramValidator())->check('ghost');

        $this->assertSame('Available', $result->status);
    }

    public function test_gpodder_net_status_pages_follow_june_parity(): void
    {
        Http::fake([
            'https://gpodder.net/user/existing/' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
            'https://gpodder.net/user/missing/' => Http::response('<html></html>', 404, ['Content-Type' => 'text/html']),
        ]);

        $validator = new \App\Services\Scanner\Validators\Generated\User\GpodderNetValidator();

        $this->assertSame('Taken', $validator->check('existing')->status);
        $this->assertSame('Available', $validator->check('missing')->status);
    }

    public function test_rubygems_200_profile_page_is_taken_and_extracts_metadata(): void
    {
        Http::fake([
            '*' => Http::response('<html><head><title>Profile of rails | RubyGems.org</title></head><body>Gems <span>42</span></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = (new RubygemsValidator())->check('rails');

        $this->assertSame('Taken', $result->status);
        $this->assertSame("Name: rails\nGems Count: 42", $result->extra);
    }

    public function test_github_api_profile_is_taken_and_extracts_rich_metadata(): void
    {
        Http::fake([
            'https://api.github.com/users/torvalds' => Http::response([
                'name' => 'Linus Torvalds',
                'bio' => 'Builder',
                'company' => 'Linux Foundation',
                'location' => 'Portland',
                'blog' => 'https://torvalds.example',
                'email' => 'linus@example.com',
                'followers' => 1000,
                'following' => 0,
                'avatar_url' => 'https://avatars.example/linus.png',
                'twitter_username' => 'linus',
                'public_repos' => 8,
                'created_at' => '2011-09-03T15:26:22Z',
            ], 200, ['Content-Type' => 'application/json']),
            'https://api.github.com/users/torvalds/social_accounts' => Http::response([
                ['url' => 'https://social.example/linus'],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = (new GithubValidator())->check('torvalds');

        $this->assertSame('Taken', $result->status);
        $this->assertStringContainsString('Name: Linus Torvalds', $result->extra);
        $this->assertStringContainsString('Bio: Builder', $result->extra);
        $this->assertStringContainsString('Website: https://torvalds.example', $result->extra);
        $this->assertStringContainsString('Email: linus@example.com', $result->extra);
        $this->assertStringContainsString('Followers: 1000', $result->extra);
        $this->assertStringContainsString('Links: https://torvalds.example, https://social.example/linus', $result->extra);
    }

    public function test_github_html_fallback_marks_existing_profile_as_taken_when_api_is_unavailable(): void
    {
        Http::fake([
            'https://api.github.com/users/sindresorhus' => Http::response(['message' => 'rate limited'], 403, ['Content-Type' => 'application/json']),
            'https://github.com/sindresorhus' => Http::response(
                '<html><head><meta property="og:image" content="https://avatars.example/sindre.png"></head><body><span itemprop="name">Sindre Sorhus</span><a href="mailto:sindre@example.com"></a></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = (new GithubValidator())->check('sindresorhus');

        $this->assertSame('Taken', $result->status);
        $this->assertStringContainsString('Name: Sindre Sorhus', $result->extra);
        $this->assertStringContainsString('Email: sindre@example.com', $result->extra);
    }

    public function test_kick_json_200_response_is_taken(): void
    {
        Http::fake([
            '*' => Http::response(['id' => 123, 'slug' => 'hienyimba', 'is_banned' => false], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = (new KickValidator())->check('hienyimba');

        $this->assertSame('Taken', $result->status);
    }

    public function test_hashnode_200_profile_page_is_taken(): void
    {
        Http::fake([
            '*' => Http::response('<html><head><title>Hashnode</title><meta property="og:title" content="Hashnode" /></head><body><h2>Available for</h2></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = (new HashnodeValidator())->check('hashnode');

        $this->assertSame('Taken', $result->status);
    }

    public function test_hashnode_200_non_profile_page_is_available(): void
    {
        Http::fake([
            '*' => Http::response('<html><head><title>Hashnode</title></head><body><div>Join the dev community</div></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = (new HashnodeValidator())->check('ghost');

        $this->assertSame('Available', $result->status);
    }

    public function test_boot_dev_profile_page_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <meta property="og:title" content="Kaif Codec" />
                    <meta property="og:description" content="Shipping backend systems." />
                    <meta property="og:image" content="https://images.example/bootdev-avatar.jpg" />
                </head>
                <body>
                    <script>window.__NUXT__={data:[{"publicUser:kaifcodec":{"name":"Kaif Codec"}}]};</script>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'boot_dev',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://boot.dev/u/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Shipping backend systems.', $result->metadata['bio']);
        $this->assertSame('https://images.example/bootdev-avatar.jpg', $result->metadata['avatar_url']);
        $this->assertContains('opengraph', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_boot_dev_user_not_found_page_is_available(): void
    {
        Http::fake([
            '*' => Http::response('<html><body>User not found</body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = (new \App\Services\Scanner\Validators\Generated\User\BootDevValidator())->check('ghost');

        $this->assertSame('Available', $result->status);
    }

    public function test_kaggle_200_profile_page_is_taken(): void
    {
        Http::fake([
            '*' => Http::response('<html><head><title>Serigne | Kaggle</title></head><body></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = (new KaggleValidator())->check('serigne');

        $this->assertSame('Taken', $result->status);
    }

    public function test_kaggle_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                '<html><head><title>Serigne | Kaggle</title><meta property="og:title" content="Serigne" /><meta property="og:description" content="Mostly interested by NLP competitions." /></head><body></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'kaggle',
            target: 'serigne',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.kaggle.com/serigne', $result->profileUrl);
        $this->assertSame('Serigne', $result->metadata['display_name']);
        $this->assertSame('serigne', $result->metadata['username']);
        $this->assertSame('Mostly interested by NLP competitions.', $result->metadata['bio']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
        $this->assertSame('Name: Serigne', $result->extra);
    }

    public function test_pinterest_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <title>Kaif Codec (kaifcodec) - Profile | Pinterest</title>
                    <meta property="og:title" content="Kaif Codec (kaifcodec) - Profile | Pinterest" />
                </head>
                <body>
                    <script id="__PWS_INITIAL_PROPS__" type="application/json">{"initialReduxState":{"users":{"":{"id":"unused"},"123":{"full_name":"Kaif Codec","about":"Shipping visual systems.","follower_count":120,"following_count":45,"board_count":12,"pin_count":340,"website_url":"https://kaif.dev","image_xlarge_url":"https://images.example/pinterest-avatar.jpg"}}}}</script>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'pinterest',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.pinterest.com/kaifcodec/', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Shipping visual systems.', $result->metadata['bio']);
        $this->assertSame(120, $result->metadata['followers']);
        $this->assertSame(45, $result->metadata['following']);
        $this->assertSame(12, $result->metadata['boards_count']);
        $this->assertSame(340, $result->metadata['pins_count']);
        $this->assertSame(340, $result->metadata['posts_count']);
        $this->assertSame('https://kaif.dev', $result->metadata['website_url']);
        $this->assertSame('https://images.example/pinterest-avatar.jpg', $result->metadata['avatar_url']);
        $this->assertContains('https://kaif.dev', $result->metadata['external_links']);
        $this->assertContains('html_hydration', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_snapchat_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <title>Kaif Codec on Snapchat</title>
                    <meta property="og:title" content="Kaif Codec on Snapchat" />
                </head>
                <body>
                    <script id="__NEXT_DATA__" type="application/json">{"props":{"pageProps":{"userProfile":{"userInfo":{"displayName":"Kaif Codec","snapcodeImageUrl":"https://images.example/snapcode.png"}}}}}</script>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'snapchat',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.snapchat.com/@kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('https://images.example/snapcode.png', $result->metadata['snapcode_url']);
        $this->assertContains('html_hydration', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_sourceforge_profile_is_taken_and_extracts_joined_and_project_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <title>Christian Schenk / SourceForge</title>
                    <meta property="og:title" content="Christian Schenk / SourceForge" />
                </head>
                <body>
                    <h1>Christian Schenk</h1>
                    <h3>Personal Data</h3>
                    <dl>
                        <dt>Username:</dt>
                        <dd>csc</dd>
                        <dt>Joined:</dt>
                        <dd>2000-08-22 20:23:38</dd>
                    </dl>
                    <h3>Projects</h3>
                    <ul>
                        <li><a href="/p/makertf/">Makertf</a></li>
                        <li><a href="/p/miktex/">MiKTeX</a></li>
                    </ul>
                    <h3>Personal Tools</h3>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'sourceforge',
            target: 'csc',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://sourceforge.net/u/csc/', $result->profileUrl);
        $this->assertSame('Christian Schenk', $result->metadata['display_name']);
        $this->assertSame('csc', $result->metadata['username']);
        $this->assertSame('2000-08-22T20:23:38+00:00', $result->metadata['created_at']);
        $this->assertSame(2, $result->metadata['projects_count']);
        $this->assertSame([
            'https://sourceforge.net/p/makertf/',
            'https://sourceforge.net/p/miktex/',
        ], $result->metadata['external_links']);
        $this->assertContains('html_profile', $result->metadata['sources']);
        $this->assertContains('projects_count', $result->metadata['evidence']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_behance_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <title>Kaif Codec on Behance</title>
                    <meta property="og:title" content="Kaif Codec on Behance" />
                </head>
                <body>
                    <script type="application/json" id="beconfig-store_state">{"profile":{"user":{"displayName":"Kaif Codec","location":"Lagos, Nigeria","company":"WebVetted","stats":{"followers":321,"following":18,"views":9876}}}}</script>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'behance',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.behance.net/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame('WebVetted', $result->metadata['company']);
        $this->assertSame(321, $result->metadata['followers']);
        $this->assertSame(18, $result->metadata['following']);
        $this->assertSame(9876, $result->metadata['views_count']);
        $this->assertContains('html_hydration', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_mastodon_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => '109876',
                'display_name' => 'Kaif Codec',
                'note' => '<p>Building metadata tooling.</p>',
                'followers_count' => 901,
                'following_count' => 87,
                'statuses_count' => 456,
                'avatar' => 'https://files.mastodon.social/accounts/avatars/avatar.png',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'mastodon',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://mastodon.social/@kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('109876', $result->metadata['mastodon_id']);
        $this->assertSame('Building metadata tooling.', $result->metadata['bio']);
        $this->assertSame(901, $result->metadata['followers']);
        $this->assertSame(87, $result->metadata['following']);
        $this->assertSame(456, $result->metadata['posts_count']);
        $this->assertSame('https://files.mastodon.social/accounts/avatars/avatar.png', $result->metadata['avatar_url']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_picsart_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'success',
                'id' => 5151,
                'name' => 'Kaif Codec',
                'status_message' => 'Making creative tools.',
                'followers_count' => 640,
                'following_count' => 53,
                'likes_count' => 1200,
                'photos_count' => 88,
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'picsart',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://picsart.com/u/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame(5151, $result->metadata['picsart_id']);
        $this->assertSame('Making creative tools.', $result->metadata['bio']);
        $this->assertSame(640, $result->metadata['followers']);
        $this->assertSame(53, $result->metadata['following']);
        $this->assertSame(1200, $result->metadata['likes_count']);
        $this->assertSame(88, $result->metadata['photos_count']);
        $this->assertSame(88, $result->metadata['posts_count']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_gumroad_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                '<html><head><title>Sahil Lavingia</title><meta property="og:title" content="Subscribe to Sahil Lavingia" /></head><body></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'gumroad',
            target: 'sahil',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://sahil.gumroad.com', $result->profileUrl);
        $this->assertSame('Sahil Lavingia', $result->metadata['display_name']);
        $this->assertSame('sahil', $result->metadata['username']);
        $this->assertSame('Sahil Lavingia', $result->metadata['profile_title']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(3, $result->metadata['observed_metadata_level']);
        $this->assertSame('Name: Sahil Lavingia', $result->extra);
    }

    public function test_producthunt_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <title>Ryan Hoover's profile | Product Hunt</title>
                </head>
                <body>
                    <script type="application/ld+json">{"name":"Ryan Hoover","url":"https://www.producthunt.com/@rrhoover"}</script>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'producthunt',
            target: 'rrhoover',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.producthunt.com/@rrhoover', $result->profileUrl);
        $this->assertSame('Ryan Hoover', $result->metadata['display_name']);
        $this->assertSame('rrhoover', $result->metadata['username']);
        $this->assertSame('Ryan Hoover\'s profile | Product Hunt', $result->metadata['profile_title']);
        $this->assertSame('https://www.producthunt.com/@rrhoover', $result->metadata['producthunt_url']);
        $this->assertContains('https://www.producthunt.com/@rrhoover', $result->metadata['external_links']);
        $this->assertContains('jsonld', $result->metadata['sources']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_codeberg_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 4242,
                'login' => 'kaifcodec',
                'full_name' => 'Kaif Codec',
                'email' => 'kaif@example.com',
                'created' => '2021-03-04T05:06:07Z',
                'location' => 'Lagos, Nigeria',
                'website' => 'https://kaif.dev',
                'avatar_url' => 'https://codeberg.org/avatars/kaif.png',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'codeberg',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://codeberg.org/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame(4242, $result->metadata['codeberg_id']);
        $this->assertSame('kaif@example.com', $result->metadata['public_email']);
        $this->assertSame('2021-03-04T05:06:07+00:00', $result->metadata['created_at']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame('https://kaif.dev', $result->metadata['website_url']);
        $this->assertSame('https://codeberg.org/avatars/kaif.png', $result->metadata['avatar_url']);
        $this->assertContains('https://kaif.dev', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_coderwall_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 8181,
                'username' => 'kaifcodec',
                'name' => 'Kaif Codec',
                'location' => 'Lagos, Nigeria',
                'karma' => 99,
                'company' => 'WebVetted',
                'about' => 'Writing parsers and shipping tools.',
                'thumbnail' => 'https://coderwall.com/avatar.png',
                'accounts' => [
                    'github' => 'kaifcodec',
                    'twitter' => 'kaifcodec',
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'coderwall',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://coderwall.com/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame(8181, $result->metadata['coderwall_id']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame(99, $result->metadata['karma']);
        $this->assertSame('WebVetted', $result->metadata['company']);
        $this->assertSame('Writing parsers and shipping tools.', $result->metadata['bio']);
        $this->assertSame('https://coderwall.com/avatar.png', $result->metadata['avatar_url']);
        $this->assertSame('kaifcodec', $result->metadata['github_handle']);
        $this->assertSame('kaifcodec', $result->metadata['twitter_handle']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_issuu_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'rsp' => [
                    '_content' => [
                        'profile' => [
                            'displayName' => 'Kaif Codec',
                            'about' => 'Publishing technical docs.',
                            'location' => 'Lagos, Nigeria',
                            'website' => 'https://kaif.dev',
                        ],
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'issuu',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://issuu.com/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Publishing technical docs.', $result->metadata['bio']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame('https://kaif.dev', $result->metadata['website_url']);
        $this->assertContains('https://kaif.dev', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_livejournal_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html><body><script>Site.journal = {"id":606,"display_username":"Kaif Codec","is_paid":true,"is_community":false};</script></body></html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'livejournal',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://kaifcodec.livejournal.com', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame(606, $result->metadata['livejournal_uid']);
        $this->assertTrue((bool) $result->metadata['is_paid']);
        $this->assertFalse((bool) $result->metadata['is_community']);
        $this->assertSame('user', $result->metadata['account_type']);
        $this->assertContains('html_hydration', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_livejournal_403_is_treated_as_taken_suspended_account(): void
    {
        Http::fake([
            '*' => Http::response('forbidden', 403, ['Content-Type' => 'text/html']),
        ]);

        $result = (new LivejournalValidator())->check('kaifcodec');

        $this->assertSame('Taken', $result->status);
        $this->assertSame('Status: suspended or forbidden', $result->extra);
    }

    public function test_hackerrank_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'model' => [
                    'id' => 7001,
                    'username' => 'kaifcodec',
                    'country' => 'Nigeria',
                    'school' => 'University of Lagos',
                    'created_at' => '2020-04-05T06:07:08Z',
                    'level' => 7,
                    'company' => 'WebVetted',
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'hackerrank',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.hackerrank.com/kaifcodec', $result->profileUrl);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame(7001, $result->metadata['hackerrank_id']);
        $this->assertSame('Nigeria', $result->metadata['location']);
        $this->assertSame('University of Lagos', $result->metadata['school']);
        $this->assertSame('2020-04-05T06:07:08+00:00', $result->metadata['created_at']);
        $this->assertSame(7, $result->metadata['hackerrank_level']);
        $this->assertSame('WebVetted', $result->metadata['company']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_liberapay_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                '<html><head><title>Kaif Codec&#39;s profile on Liberapay</title></head><body></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'liberapay',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://en.liberapay.com/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame("Kaif Codec's profile on Liberapay", $result->metadata['profile_title']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_blogger_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                '<html><head><title>Kaif Codec Dev Log</title></head><body></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'blogger',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://kaifcodec.blogspot.com/', $result->profileUrl);
        $this->assertSame('Kaif Codec Dev Log', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Kaif Codec Dev Log', $result->metadata['profile_title']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_lastfm_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html><body>
                <h1 class="header-title-display-name">Kaif Codec</h1>
                <p>scrobbling since 5 April 2020</p>
                <section>Scrobbles<p><a>12,345</a></p></section>
                <section>Artists<p><a>678</a></p></section>
                <img src="https://lastfm.example/avatar.png" alt="Avatar for Kaif Codec" itemprop="image" />
                </body></html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'lastfm',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.last.fm/user/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('2020-04-05T00:00:00+00:00', $result->metadata['created_at']);
        $this->assertSame('5 April 2020', $result->metadata['scrobbling_since']);
        $this->assertSame(12345, $result->metadata['scrobbles_count']);
        $this->assertSame(12345, $result->metadata['posts_count']);
        $this->assertSame(678, $result->metadata['artists_count']);
        $this->assertSame('https://lastfm.example/avatar.png', $result->metadata['avatar_url']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_itchio_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                '<html><head><title>Kaif Codec - itch.io</title></head><body></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'itch_io',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://itch.io/profile/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Kaif Codec', $result->metadata['profile_title']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(3, $result->metadata['observed_metadata_level']);
    }

    public function test_patreon_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html><body><script type="application/ld+json">{"name":"Kaif Codec","url":"https://www.patreon.com/kaifcodec"}</script></body></html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'patreon',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.patreon.com/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('https://www.patreon.com/kaifcodec', $result->metadata['patreon_url']);
        $this->assertContains('https://www.patreon.com/kaifcodec', $result->metadata['external_links']);
        $this->assertContains('jsonld', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_boosty_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'owner' => [
                    'id' => 991,
                    'name' => 'Kaif Codec',
                    'externalApps' => [
                        'discord' => ['hasAccount' => true],
                        'telegram' => ['hasAccount' => true],
                    ],
                ],
                'count' => [
                    'subscribers' => 1234,
                    'posts' => 77,
                ],
                'title' => 'Build notes and private essays',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'boosty',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://boosty.to/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame(991, $result->metadata['boosty_id']);
        $this->assertSame(1234, $result->metadata['subscribers_count']);
        $this->assertSame(1234, $result->metadata['followers']);
        $this->assertSame(77, $result->metadata['posts_count']);
        $this->assertSame('Build notes and private essays', $result->metadata['profile_title']);
        $this->assertTrue((bool) $result->metadata['has_discord']);
        $this->assertTrue((bool) $result->metadata['has_telegram']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_asciinema_200_profile_page_is_taken(): void
    {
        Http::fake([
            '*' => Http::response('<html><head><title>asciinema&#39;s profile - asciinema.org</title></head><body></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = (new AsciinemaValidator())->check('asciinema');

        $this->assertSame('Taken', $result->status);
    }

    public function test_arduino_json_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'user' => [
                    'id' => 995775,
                    'username' => 'arduino',
                    'name' => 'arduino__',
                    'avatar_template' => 'https://avatars.discourse-cdn.com/v4/letter/a/c89c15/{size}.png',
                    'profile_hidden' => true,
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'arduino',
            target: 'arduino',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://forum.arduino.cc/u/arduino', $result->profileUrl);
        $this->assertSame('arduino__', $result->metadata['display_name']);
        $this->assertSame('arduino', $result->metadata['username']);
        $this->assertSame('https://avatars.discourse-cdn.com/v4/letter/a/c89c15/512.png', $result->metadata['avatar_url']);
        $this->assertTrue((bool) $result->metadata['is_private']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_mozilla_discourse_json_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'user' => [
                    'id' => 141,
                    'username' => 'pmac',
                    'name' => 'Paul McLanahan',
                    'avatar_template' => '/user_avatar/discourse.mozilla.org/pmac/{size}/20548_2.png',
                    'last_posted_at' => '2019-03-14T17:25:49.582Z',
                    'last_seen_at' => '2022-03-17T19:18:20.782Z',
                    'created_at' => '2014-03-04T22:04:14.630Z',
                    'location' => 'Remote',
                    'website' => 'https://people.mozilla.org/p/pmac',
                    'bio_excerpt' => 'I manage the team working on Mozilla web properties.',
                    'bio_cooked' => '<p>I manage the team working on <a href="https://www.mozilla.org">www.mozilla.org</a>.</p>',
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'mozilladiscourse',
            target: 'pmac',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://discourse.mozilla.org/u/pmac', $result->profileUrl);
        $this->assertSame('Paul McLanahan', $result->metadata['display_name']);
        $this->assertSame('pmac', $result->metadata['username']);
        $this->assertSame('https://discourse.mozilla.org/user_avatar/discourse.mozilla.org/pmac/512/20548_2.png', $result->metadata['avatar_url']);
        $this->assertSame('I manage the team working on Mozilla web properties.', $result->metadata['bio']);
        $this->assertSame('Remote', $result->metadata['location']);
        $this->assertSame('https://people.mozilla.org/p/pmac', $result->metadata['website_url']);
        $this->assertSame('2014-03-04T22:04:14+00:00', $result->metadata['created_at']);
        $this->assertSame('2022-03-17T19:18:20+00:00', $result->metadata['last_active_at']);
        $this->assertContains('https://www.mozilla.org', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_dailymotion_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'x43py0',
                'username' => 'dailymotion',
                'screenname' => 'Dailymotion',
                'description' => '',
                'avatar_720_url' => 'https://s2.dmcdn.net/u/QIeO1gIH8tcP8nxo/720x720',
                'followers_total' => 11656,
                'following_total' => 103,
                'videos_total' => 131,
                'country' => 'FR',
                'created_time' => 1179315946,
                'verified' => true,
                'url' => 'https://www.dailymotion.com/dailymotion',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'dailymotion',
            target: 'dailymotion',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.dailymotion.com/dailymotion', $result->profileUrl);
        $this->assertSame('Dailymotion', $result->metadata['display_name']);
        $this->assertSame('dailymotion', $result->metadata['username']);
        $this->assertSame('https://s2.dmcdn.net/u/QIeO1gIH8tcP8nxo/720x720', $result->metadata['avatar_url']);
        $this->assertSame('FR', $result->metadata['location']);
        $this->assertSame(11656, $result->metadata['followers']);
        $this->assertSame(103, $result->metadata['following']);
        $this->assertSame(131, $result->metadata['posts_count']);
        $this->assertTrue((bool) $result->metadata['is_verified']);
        $this->assertSame('2007-05-16T11:45:46+00:00', $result->metadata['created_at']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_github_gist_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'login' => 'sindresorhus',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/170270?v=4',
                'html_url' => 'https://github.com/sindresorhus',
                'type' => 'User',
                'name' => 'Sindre Sorhus',
                'blog' => 'https://sindresorhus.com/apps',
                'bio' => 'Full-Time Open-Sourcerer.',
                'twitter_username' => 'sindresorhus',
                'public_repos' => 1137,
                'followers' => 80015,
                'following' => 31,
                'created_at' => '2009-12-20T22:57:02Z',
                'updated_at' => '2026-06-19T19:20:46Z',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'githubgist',
            target: 'sindresorhus',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://gist.github.com/sindresorhus', $result->profileUrl);
        $this->assertSame('Sindre Sorhus', $result->metadata['display_name']);
        $this->assertSame('sindresorhus', $result->metadata['username']);
        $this->assertSame('https://avatars.githubusercontent.com/u/170270?v=4', $result->metadata['avatar_url']);
        $this->assertSame('Full-Time Open-Sourcerer.', $result->metadata['bio']);
        $this->assertSame('https://sindresorhus.com/apps', $result->metadata['website_url']);
        $this->assertSame(80015, $result->metadata['followers']);
        $this->assertSame(31, $result->metadata['following']);
        $this->assertSame(1137, $result->metadata['posts_count']);
        $this->assertSame('2009-12-20T22:57:02+00:00', $result->metadata['created_at']);
        $this->assertSame('2026-06-19T19:20:46+00:00', $result->metadata['last_active_at']);
        $this->assertSame('User', $result->metadata['account_type']);
        $this->assertContains('https://x.com/sindresorhus', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_gitea_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 1,
                'login' => 'lunny',
                'full_name' => 'Lunny Xiao',
                'email' => '1+lunny@noreply.gitea.com',
                'avatar_url' => 'https://gitea.com/avatars/5739143ce171af62d1ff0baa027dacfd5e5ffe9c28b7b92fc4ed02d952ab1cc3',
                'html_url' => 'https://gitea.com/lunny',
                'created' => '2018-11-27T17:33:38Z',
                'location' => 'Silicon Valley',
                'website' => 'https://gitea.com',
                'description' => 'Builder',
                'visibility' => 'public',
                'followers_count' => 113,
                'following_count' => 94,
                'starred_repos_count' => 361,
                'username' => 'lunny',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'gitea',
            target: 'lunny',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://gitea.com/lunny', $result->profileUrl);
        $this->assertSame('Lunny Xiao', $result->metadata['display_name']);
        $this->assertSame('lunny', $result->metadata['username']);
        $this->assertSame('https://gitea.com/avatars/5739143ce171af62d1ff0baa027dacfd5e5ffe9c28b7b92fc4ed02d952ab1cc3', $result->metadata['avatar_url']);
        $this->assertSame('Builder', $result->metadata['bio']);
        $this->assertSame('Silicon Valley', $result->metadata['location']);
        $this->assertSame('https://gitea.com', $result->metadata['website_url']);
        $this->assertSame('1+lunny@noreply.gitea.com', $result->metadata['public_email']);
        $this->assertSame(113, $result->metadata['followers']);
        $this->assertSame(94, $result->metadata['following']);
        $this->assertSame(361, $result->metadata['posts_count']);
        $this->assertSame('2018-11-27T17:33:38+00:00', $result->metadata['created_at']);
        $this->assertSame('public', $result->metadata['account_type']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_px500_graphql_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'userByUsername' => [
                        'id' => '1',
                        'legacyId' => 1,
                        'username' => 'prsths',
                        'displayName' => 'Prsths',
                        'registeredAt' => '2020-04-03T12:34:56Z',
                        'userProfile' => [
                            'firstname' => 'Prasath',
                            'lastname' => '',
                            'about' => 'Photographer and traveler.',
                            'country' => 'Nigeria',
                            'city' => 'Lagos',
                            'state' => 'Lagos',
                        ],
                        'socialMedia' => [
                            'website' => 'https://portfolio.test/prsths',
                            'twitter' => 'https://twitter.com/prsths',
                            'facebook' => '',
                            'instagram' => 'https://instagram.com/prsths',
                        ],
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'px500',
            target: 'prsths',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://500px.com/prsths', $result->profileUrl);
        $this->assertSame('Prsths', $result->metadata['display_name']);
        $this->assertSame('prsths', $result->metadata['username']);
        $this->assertSame('Photographer and traveler.', $result->metadata['bio']);
        $this->assertSame('Lagos, Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame('https://portfolio.test/prsths', $result->metadata['website_url']);
        $this->assertSame('2020-04-03T12:34:56+00:00', $result->metadata['created_at']);
        $this->assertContains('https://twitter.com/prsths', $result->metadata['external_links']);
        $this->assertContains('https://instagram.com/prsths', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_cratesio_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'user' => [
                    'id' => 1,
                    'login' => 'alexcrichton',
                    'name' => 'Alex Crichton',
                    'avatar' => 'https://avatars.githubusercontent.com/u/64996?v=4',
                    'url' => 'https://github.com/alexcrichton',
                    'created_at' => '2009-03-19T19:31:50Z',
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'cratesio',
            target: 'alexcrichton',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('Alex Crichton', $result->metadata['display_name']);
        $this->assertSame('alexcrichton', $result->metadata['username']);
        $this->assertSame('https://avatars.githubusercontent.com/u/64996?v=4', $result->metadata['avatar_url']);
        $this->assertSame('https://github.com/alexcrichton', $result->metadata['website_url']);
        $this->assertSame('2009-03-19T19:31:50+00:00', $result->metadata['created_at']);
        $this->assertContains('https://github.com/alexcrichton', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_codewars_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => '545207bac8e60b30fc000942',
                'username' => 'g964',
                'honor' => 487557,
                'leaderboardPosition' => 1,
                'ranks' => [
                    'overall' => [
                        'name' => '1 kyu',
                    ],
                ],
                'codeChallenges' => [
                    'totalAuthored' => 147,
                    'totalCompleted' => 2644,
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'codewars',
            target: 'g964',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('g964', $result->metadata['display_name']);
        $this->assertSame('g964', $result->metadata['username']);
        $this->assertSame(487557, $result->metadata['honor']);
        $this->assertSame(1, $result->metadata['leaderboard_position']);
        $this->assertSame(2644, $result->metadata['code_challenges_completed']);
        $this->assertSame(147, $result->metadata['code_challenges_authored']);
        $this->assertSame('1 kyu', $result->metadata['rank_name']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_codeforces_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'OK',
                'result' => [[
                    'handle' => 'tourist',
                    'firstName' => 'Gennady',
                    'lastName' => 'Korotkevich',
                    'titlePhoto' => 'https://userpic.codeforces.org/422/title/50a5b5a74f6f5006.jpg',
                    'city' => 'Gomel',
                    'country' => 'Belarus',
                    'rank' => 'legendary grandmaster',
                    'maxRank' => 'legendary grandmaster',
                    'rating' => 3797,
                    'maxRating' => 3852,
                    'contribution' => 161,
                    'friendOfCount' => 102945,
                    'organization' => 'ITMO University',
                    'registrationTimeSeconds' => 1267723898,
                    'lastOnlineTimeSeconds' => 1719955200,
                ]],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'codeforces',
            target: 'tourist',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://codeforces.com/profile/tourist', $result->profileUrl);
        $this->assertSame('Gennady Korotkevich', $result->metadata['display_name']);
        $this->assertSame('tourist', $result->metadata['username']);
        $this->assertSame('https://userpic.codeforces.org/422/title/50a5b5a74f6f5006.jpg', $result->metadata['avatar_url']);
        $this->assertSame('Gomel, Belarus', $result->metadata['location']);
        $this->assertSame('legendary grandmaster', $result->metadata['account_type']);
        $this->assertSame(102945, $result->metadata['followers']);
        $this->assertSame(3797, $result->metadata['rating']);
        $this->assertSame(3852, $result->metadata['max_rating']);
        $this->assertSame('legendary grandmaster', $result->metadata['max_rank']);
        $this->assertSame('ITMO University', $result->metadata['organization']);
        $this->assertSame('2010-03-04T17:31:38+00:00', $result->metadata['created_at']);
        $this->assertSame('2024-07-02T21:20:00+00:00', $result->metadata['last_active_at']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_keybase_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'them' => [[
                    'basics' => [
                        'username' => 'max',
                    ],
                    'profile' => [
                        'full_name' => 'Max Krohn',
                        'bio' => 'Building secure identity.',
                        'location' => 'New York, NY',
                        'website' => 'https://keybase.io/max',
                    ],
                    'pictures' => [
                        'primary' => [
                            'url' => 'https://keybase.io/images/avatar/max.jpg',
                        ],
                    ],
                    'proofs_summary' => [
                        'all' => [
                            ['proof_url' => 'https://github.com/maxtaco'],
                            ['service_url' => 'https://x.com/maxtaco'],
                        ],
                    ],
                ]],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'keybase',
            target: 'max',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://keybase.io/max', $result->profileUrl);
        $this->assertSame('Max Krohn', $result->metadata['display_name']);
        $this->assertSame('max', $result->metadata['username']);
        $this->assertSame('https://keybase.io/images/avatar/max.jpg', $result->metadata['avatar_url']);
        $this->assertSame('Building secure identity.', $result->metadata['bio']);
        $this->assertSame('New York, NY', $result->metadata['location']);
        $this->assertSame('https://keybase.io/max', $result->metadata['website_url']);
        $this->assertSame(2, $result->metadata['proof_count']);
        $this->assertContains('https://github.com/maxtaco', $result->metadata['external_links']);
        $this->assertContains('https://x.com/maxtaco', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_reddit_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'kind' => 't2',
                'data' => [
                    'name' => 'spez',
                    'icon_img' => 'https://styles.redditmedia.com/t5_2qh33/styles/profileIcon_spez.png',
                    'created_utc' => 1134028003,
                    'verified' => true,
                    'total_karma' => 190000,
                    'link_karma' => 100000,
                    'comment_karma' => 90000,
                    'subreddit' => [
                        'title' => 'spez',
                        'public_description' => 'Co-founder of Reddit.',
                        'subscribers' => 500000,
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'reddit',
            target: 'spez',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.reddit.com/user/spez', $result->profileUrl);
        $this->assertSame('spez', $result->metadata['display_name']);
        $this->assertSame('spez', $result->metadata['username']);
        $this->assertSame('https://styles.redditmedia.com/t5_2qh33/styles/profileIcon_spez.png', $result->metadata['avatar_url']);
        $this->assertSame('Co-founder of Reddit.', $result->metadata['bio']);
        $this->assertSame(500000, $result->metadata['followers']);
        $this->assertSame(190000, $result->metadata['karma']);
        $this->assertSame(100000, $result->metadata['link_karma']);
        $this->assertSame(90000, $result->metadata['comment_karma']);
        $this->assertTrue((bool) $result->metadata['is_verified']);
        $this->assertSame('2005-12-08T07:46:43+00:00', $result->metadata['created_at']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_disqus_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => [
                    'id' => '1',
                    'username' => 'disqus',
                    'name' => 'Disqus Team',
                    'about' => 'Discussion platform.',
                    'location' => 'San Francisco, CA',
                    'url' => 'https://disqus.com',
                    'joinedAt' => '2011-04-13T00:00:00',
                    'numFollowers' => 1000,
                    'numFollowing' => 5,
                    'numPosts' => 300,
                    'isVerified' => true,
                    'avatar' => [
                        'permalink' => 'https://a.disquscdn.com/current/uploads/users/1/avatar92.jpg',
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'disqus',
            target: 'disqus',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://disqus.com/by/disqus/', $result->profileUrl);
        $this->assertSame('Disqus Team', $result->metadata['display_name']);
        $this->assertSame('disqus', $result->metadata['username']);
        $this->assertSame('https://a.disquscdn.com/current/uploads/users/1/avatar92.jpg', $result->metadata['avatar_url']);
        $this->assertSame('Discussion platform.', $result->metadata['bio']);
        $this->assertSame('San Francisco, CA', $result->metadata['location']);
        $this->assertSame('https://disqus.com', $result->metadata['website_url']);
        $this->assertSame(1000, $result->metadata['followers']);
        $this->assertSame(5, $result->metadata['following']);
        $this->assertSame(300, $result->metadata['posts_count']);
        $this->assertTrue((bool) $result->metadata['is_verified']);
        $this->assertSame('2011-04-13T00:00:00+00:00', $result->metadata['created_at']);
        $this->assertContains('https://disqus.com', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_imgur_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 42,
                'url' => 'sarah',
                'display_name' => 'Sarah',
                'avatar_url' => 'https://i.imgur.com/avatar.png',
                'bio' => 'Cats and memes.',
                'website' => 'https://portfolio.example/sarah',
                'reputation' => 4242,
                'reputation_name' => 'Trusted',
                'created_at' => 1451606400,
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'imgur',
            target: 'sarah',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://imgur.com/user/sarah', $result->profileUrl);
        $this->assertSame('Sarah', $result->metadata['display_name']);
        $this->assertSame('sarah', $result->metadata['username']);
        $this->assertSame('https://i.imgur.com/avatar.png', $result->metadata['avatar_url']);
        $this->assertSame('Cats and memes.', $result->metadata['bio']);
        $this->assertSame('https://portfolio.example/sarah', $result->metadata['website_url']);
        $this->assertSame(4242, $result->metadata['reputation']);
        $this->assertSame('Trusted', $result->metadata['reputation_name']);
        $this->assertSame('2016-01-01T00:00:00+00:00', $result->metadata['created_at']);
        $this->assertContains('https://portfolio.example/sarah', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_freelancer_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'result' => [
                    'users' => [
                        '789' => [
                            'id' => 789,
                            'username' => 'hienyimba',
                            'display_name' => 'Hieny Imba',
                            'role' => 'freelancer',
                            'registration_date' => '2020-01-02T03:04:05Z',
                            'location' => [
                                'city' => 'Lagos',
                                'country' => [
                                    'name' => 'Nigeria',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'freelancer',
            target: 'hienyimba',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.freelancer.com/u/hienyimba', $result->profileUrl);
        $this->assertSame('Hieny Imba', $result->metadata['display_name']);
        $this->assertSame('hienyimba', $result->metadata['username']);
        $this->assertSame('freelancer', $result->metadata['account_type']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame('2020-01-02T03:04:05+00:00', $result->metadata['created_at']);
        $this->assertSame(789, $result->metadata['freelancer_id']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_fansly_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'response' => [[
                    'id' => 555,
                    'displayName' => 'Creator Hieny',
                    'followCount' => 3210,
                    'timelineStats' => [
                        'imageCount' => 88,
                        'videoCount' => 12,
                    ],
                ]],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'fansly',
            target: 'hienyimba',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://fansly.com/hienyimba', $result->profileUrl);
        $this->assertSame('Creator Hieny', $result->metadata['display_name']);
        $this->assertSame('hienyimba', $result->metadata['username']);
        $this->assertSame(3210, $result->metadata['followers']);
        $this->assertSame(88, $result->metadata['images']);
        $this->assertSame(12, $result->metadata['videos']);
        $this->assertSame(555, $result->metadata['fansly_id']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_paragraph_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 98,
                'name' => 'Hieny Writes',
                'createdAt' => '2024-02-03T04:05:06.000Z',
                'userId' => 15,
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'paragraph',
            target: 'hienyimba',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://paragraph.com/@hienyimba', $result->profileUrl);
        $this->assertSame('Hieny Writes', $result->metadata['display_name']);
        $this->assertSame('hienyimba', $result->metadata['username']);
        $this->assertSame('2024-02-03T04:05:06+00:00', $result->metadata['created_at']);
        $this->assertSame(98, $result->metadata['paragraph_id']);
        $this->assertSame(15, $result->metadata['user_id']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_niftygateway_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'didSucceed' => true,
                'userProfileAndNifties' => [
                    'id' => 77,
                    'user_id' => 88,
                    'name' => 'Hieny Collector',
                    'bio' => 'Collecting digital art.',
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'niftygateway',
            target: 'hienyimba',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://niftygateway.com/profile/hienyimba', $result->profileUrl);
        $this->assertSame('Hieny Collector', $result->metadata['display_name']);
        $this->assertSame('hienyimba', $result->metadata['username']);
        $this->assertSame('Collecting digital art.', $result->metadata['bio']);
        $this->assertSame(77, $result->metadata['niftygateway_id']);
        $this->assertSame(88, $result->metadata['user_id']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_spotify_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'item' => [
                    'displayName' => 'Kaif Codec',
                    'image' => 'https://cdn.stats.fm/avatar.png',
                    'createdAt' => '2024-01-02T03:04:05.000Z',
                    'timezone' => 'Africa/Lagos',
                    'isPro' => true,
                    'isPlus' => false,
                    'profile' => [
                        'bio' => 'Music all day.',
                        'pronouns' => 'he/him',
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'spotify',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://open.spotify.com/user/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('https://cdn.stats.fm/avatar.png', $result->metadata['avatar_url']);
        $this->assertSame('Music all day.', $result->metadata['bio']);
        $this->assertSame('2024-01-02T03:04:05+00:00', $result->metadata['created_at']);
        $this->assertSame('Africa/Lagos', $result->metadata['timezone']);
        $this->assertSame('he/him', $result->metadata['pronouns']);
        $this->assertTrue((bool) $result->metadata['is_pro']);
        $this->assertFalse((bool) $result->metadata['is_plus']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_calendly_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'name' => 'Kaif Codec',
                'description' => 'Book a time to chat.',
                'avatar_url' => 'https://d3v0px0pttie1i.cloudfront.net/uploads/user/avatar.png',
                'organization_uuid' => 'org_12345',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'calendly',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://calendly.com/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Book a time to chat.', $result->metadata['bio']);
        $this->assertSame('https://d3v0px0pttie1i.cloudfront.net/uploads/user/avatar.png', $result->metadata['avatar_url']);
        $this->assertSame('org_12345', $result->metadata['organization_uuid']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_twitch_graphql_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([[
                'data' => [
                    'user' => [
                        '__typename' => 'User',
                        'id' => '71092938',
                        'description' => 'Variety streaming.',
                        'followers' => [
                            'totalCount' => 999999,
                        ],
                        'channel' => [
                            'socialMedias' => [
                                ['name' => 'Twitter', 'url' => 'https://x.com/streamer'],
                                ['name' => 'YouTube', 'url' => 'https://youtube.com/@streamer'],
                            ],
                        ],
                    ],
                ],
            ]], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'twitch',
            target: 'xqcow',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://twitch.tv/xqcow', $result->profileUrl);
        $this->assertSame('xqcow', $result->metadata['username']);
        $this->assertSame('Variety streaming.', $result->metadata['bio']);
        $this->assertSame(999999, $result->metadata['followers']);
        $this->assertSame(71092938, $result->metadata['twitch_id']);
        $this->assertSame('https://x.com/streamer', $result->metadata['twitter']);
        $this->assertSame('https://youtube.com/@streamer', $result->metadata['youtube']);
        $this->assertContains('https://x.com/streamer', $result->metadata['external_links']);
        $this->assertContains('https://youtube.com/@streamer', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_beatstars_graphql_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'identifierAvailable' => [
                        'available' => false,
                        'profileDetails' => [
                            'username' => 'kaifcodec',
                            'artwork' => [
                                'fitInUrl' => 'https://cdn.beatstars.com/artworks/fit.png',
                            ],
                        ],
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'beatstars',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.beatstars.com/kaifcodec', $result->profileUrl);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('https://cdn.beatstars.com/artworks/fit.png', $result->metadata['avatar_url']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(3, $result->metadata['observed_metadata_level']);
    }

    public function test_leetcode_graphql_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'matchedUser' => [
                        'username' => 'kaifcodec',
                        'profile' => [
                            'realName' => 'Kaif O.',
                            'aboutMe' => 'Competitive programmer.',
                            'userAvatar' => 'https://assets.leetcode.com/avatar.png',
                            'countryName' => 'Nigeria',
                            'company' => 'OpenAI',
                            'school' => 'UNILAG',
                            'ranking' => 12345,
                        ],
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'leetcode',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://leetcode.com/u/kaifcodec/', $result->profileUrl);
        $this->assertSame('Kaif O.', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Competitive programmer.', $result->metadata['bio']);
        $this->assertSame('https://assets.leetcode.com/avatar.png', $result->metadata['avatar_url']);
        $this->assertSame('Nigeria', $result->metadata['location']);
        $this->assertSame('OpenAI', $result->metadata['company']);
        $this->assertSame('UNILAG', $result->metadata['school']);
        $this->assertSame(12345, $result->metadata['ranking']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_scratch_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 445566,
                'history' => [
                    'joined' => '2017-09-10T12:13:14.000Z',
                ],
                'profile' => [
                    'images' => [
                        '90x90' => 'https://cdn2.scratch.mit.edu/get_image/user/445566_90x90.png',
                    ],
                    'country' => 'Nigeria',
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'scratch',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://scratch.mit.edu/users/kaifcodec', $result->profileUrl);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('https://cdn2.scratch.mit.edu/get_image/user/445566_90x90.png', $result->metadata['avatar_url']);
        $this->assertSame('Nigeria', $result->metadata['location']);
        $this->assertSame('2017-09-10T12:13:14+00:00', $result->metadata['created_at']);
        $this->assertSame(445566, $result->metadata['scratch_id']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_stackoverflow_search_result_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <body>
                    <div class="grid--item user-info">
                        <div class="user-details">
                            <a href="/users/123456/kaifcodec">kaifcodec</a>
                        </div>
                        <span class="user-location">Lagos, Nigeria</span>
                        <span title="this user has a total reputation: 12,345">12.3k</span>
                        <img src="//www.gravatar.com/avatar/abcdef?s=64&amp;d=identicon&amp;r=PG">
                    </div>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'stackoverflow',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://stackoverflow.com/users/filter?search=kaifcodec', $result->profileUrl);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame('12,345', $result->metadata['reputation']);
        $this->assertSame('https://www.gravatar.com/avatar/abcdef?s=64&d=identicon&r=PG', $result->metadata['avatar_url']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_trello_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'abc123',
                'fullName' => 'Kaif Codec',
                'bio' => 'Shipping boards.',
                'initials' => 'KC',
                'username' => 'kaifcodec',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'trello',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://trello.com/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Shipping boards.', $result->metadata['bio']);
        $this->assertSame('KC', $result->metadata['initials']);
        $this->assertSame('abc123', $result->metadata['trello_id']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_vimeo_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 314159,
                'display_name' => 'Vimeo Creator',
                'created_on' => '2018-11-27 17:33:38 +00:00',
                'location' => 'New York, NY',
                'bio' => 'Filmmaker and editor.',
                'total_videos_uploaded' => 64,
                'total_contacts' => 120,
                'total_channels' => 8,
                'total_videos_liked' => 410,
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'vimeo',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://vimeo.com/kaifcodec', $result->profileUrl);
        $this->assertSame('Vimeo Creator', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('New York, NY', $result->metadata['location']);
        $this->assertSame('Filmmaker and editor.', $result->metadata['bio']);
        $this->assertSame('2018-11-27T17:33:38+00:00', $result->metadata['created_at']);
        $this->assertSame(64, $result->metadata['videos']);
        $this->assertSame(120, $result->metadata['contacts']);
        $this->assertSame(8, $result->metadata['channels']);
        $this->assertSame(410, $result->metadata['liked']);
        $this->assertSame(314159, $result->metadata['vimeo_id']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_statsfm_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'item' => [
                    'id' => 9090,
                    'displayName' => 'Kaif on stats.fm',
                    'createdAt' => '2024-03-04T05:06:07.000Z',
                    'isPlus' => true,
                    'isPro' => false,
                    'quarantined' => false,
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'statsfm',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://stats.fm/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif on stats.fm', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('2024-03-04T05:06:07+00:00', $result->metadata['created_at']);
        $this->assertSame(9090, $result->metadata['statsfm_id']);
        $this->assertTrue((bool) $result->metadata['is_plus']);
        $this->assertFalse((bool) $result->metadata['is_pro']);
        $this->assertFalse((bool) $result->metadata['quarantined']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_gravatar_json_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'entry' => [[
                    'hash' => 'abc123hash',
                    'thumbnailUrl' => 'https://secure.gravatar.com/avatar/abc123hash',
                    'preferredUsername' => 'kaifcodec',
                    'displayName' => 'Kaif Codec',
                    'aboutMe' => 'Public profile bio.',
                    'currentLocation' => 'Lagos, Nigeria',
                    'emails' => [
                        ['value' => 'kaif@example.com'],
                    ],
                    'accounts' => [
                        ['url' => 'https://github.com/kaifcodec'],
                    ],
                    'urls' => [
                        ['value' => 'https://kaifcodec.dev'],
                    ],
                ]],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'gravatar',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://gravatar.com/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('https://secure.gravatar.com/avatar/abc123hash', $result->metadata['avatar_url']);
        $this->assertSame('Public profile bio.', $result->metadata['bio']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame('kaif@example.com', $result->metadata['public_email']);
        $this->assertSame('abc123hash', $result->metadata['gravatar_id']);
        $this->assertContains('https://github.com/kaifcodec', $result->metadata['external_links']);
        $this->assertContains('https://kaifcodec.dev', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_steam_profile_page_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head><title>Steam Community :: kaifcodec</title></head>
                <body>
                    <script>
                        var profileData = {"steamid":"76561198000000000","personaname":"Kaif Persona","summary":"Competitive gamer"};
                    </script>
                    <div class="header_real_name ellipsis"><bdi>Kaif Realname</bdi></div>
                    <div class="header_location"><img src="/public/images/countryflags/ng.gif">Lagos, Nigeria</div>
                    <div class="playerAvatar profile_header_size">
                        <picture><img srcset="https://avatars.fastly.steamstatic.com/avatar_full.jpg"></picture>
                    </div>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'steam',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://steamcommunity.com/id/kaifcodec/', $result->profileUrl);
        $this->assertSame('Kaif Persona', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Kaif Realname', $result->metadata['real_name']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame('Competitive gamer', $result->metadata['bio']);
        $this->assertSame('https://avatars.fastly.steamstatic.com/avatar_full.jpg', $result->metadata['avatar_url']);
        $this->assertSame('76561198000000000', $result->metadata['steam_id']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_openstreetmap_profile_page_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <body>
                    <dl>
                        <dt>Mapper since:</dt>
                        <dd>2018-02-03 04:05:06 UTC</dd>
                    </dl>
                    <img class="user_image" src="/avatars/users/kaifcodec.png">
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'openstreetmap',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.openstreetmap.org/user/kaifcodec', $result->profileUrl);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('2018-02-03T04:05:06+00:00', $result->metadata['created_at']);
        $this->assertSame('https://www.openstreetmap.org/avatars/users/kaifcodec.png', $result->metadata['avatar_url']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(3, $result->metadata['observed_metadata_level']);
    }

    public function test_warframemarket_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'id' => 444,
                    'role' => 'seller',
                    'ingameName' => 'KaifPrime',
                    'reputation' => 321,
                    'masteryRank' => 18,
                    'status' => 'online',
                    'lastSeen' => '2026-07-04T10:11:12.000Z',
                    'platform' => 'pc',
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'warframemarket',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://warframe.market/profile/kaifcodec', $result->profileUrl);
        $this->assertSame('KaifPrime', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('seller', $result->metadata['account_type']);
        $this->assertSame('KaifPrime', $result->metadata['ingame_name']);
        $this->assertSame(321, $result->metadata['reputation']);
        $this->assertSame(18, $result->metadata['mastery_rank']);
        $this->assertSame('online', $result->metadata['status']);
        $this->assertSame('2026-07-04T10:11:12+00:00', $result->metadata['last_active_at']);
        $this->assertSame('pc', $result->metadata['market_platform']);
        $this->assertSame(444, $result->metadata['warframemarket_id']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_vivino_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 5151,
                'alias' => 'Kaif Wines',
                'seo_name' => 'kaif-wines',
                'is_premium' => true,
                'image' => [
                    'location' => '//images.vivino.com/users/kaif.png',
                ],
                'language' => 'en',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'vivino',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.vivino.com/users/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Wines', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Kaif Wines', $result->metadata['alias']);
        $this->assertSame('kaif-wines', $result->metadata['seo_name']);
        $this->assertTrue((bool) $result->metadata['is_premium']);
        $this->assertSame('https://images.vivino.com/users/kaif.png', $result->metadata['avatar_url']);
        $this->assertSame('en', $result->metadata['language']);
        $this->assertSame(5151, $result->metadata['vivino_id']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_donatealerts_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'name' => 'Kaif Alerts',
                    'preferred_currency' => 'USD',
                    'avatar' => 'https://static.donationalerts.ru/avatar.png',
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'donatealerts',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.donationalerts.com/r/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Alerts', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('USD', $result->metadata['currency']);
        $this->assertSame('https://static.donationalerts.ru/avatar.png', $result->metadata['avatar_url']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_omglol_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => [
                    'address' => 'kaifcodec',
                    'message' => 'hello from omg.lol',
                    'registration' => [
                        'date' => '2024-01-02T03:04:05.000Z',
                        'expiration' => '2027-01-02T03:04:05.000Z',
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'omglol',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://omg.lol/kaifcodec', $result->profileUrl);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('hello from omg.lol', $result->metadata['bio']);
        $this->assertSame('hello from omg.lol', $result->metadata['message']);
        $this->assertSame('2024-01-02T03:04:05+00:00', $result->metadata['created_at']);
        $this->assertSame('2027-01-02T03:04:05+00:00', $result->metadata['expiration_at']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_dockerhub_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 2024,
                'full_name' => 'Kaif Containers',
                'company' => 'OpenAI',
                'location' => 'San Francisco, CA',
                'date_joined' => '2021-05-06T07:08:09.000Z',
                'type' => 'user',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'dockerhub',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://hub.docker.com/u/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Containers', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('OpenAI', $result->metadata['company']);
        $this->assertSame('San Francisco, CA', $result->metadata['location']);
        $this->assertSame('2021-05-06T07:08:09+00:00', $result->metadata['created_at']);
        $this->assertSame('user', $result->metadata['account_type']);
        $this->assertSame(2024, $result->metadata['dockerhub_id']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_gitee_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 7788,
                'login' => 'kaifcodec',
                'name' => 'Kaif Gitee',
                'bio' => 'Shipping code on Gitee.',
                'blog' => 'https://kaif.example',
                'public_repos' => 42,
                'followers' => 120,
                'following' => 18,
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'gitee',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://gitee.com/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Gitee', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Shipping code on Gitee.', $result->metadata['bio']);
        $this->assertSame('https://kaif.example', $result->metadata['website_url']);
        $this->assertSame(42, $result->metadata['posts_count']);
        $this->assertSame(120, $result->metadata['followers']);
        $this->assertSame(18, $result->metadata['following']);
        $this->assertSame(7788, $result->metadata['gitee_id']);
        $this->assertContains('https://kaif.example', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_dribbble_about_page_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <link rel="canonical" href="https://dribbble.com/kaifcodec">
                </head>
                <body>
                    <h1 class="masthead-profile-name">Kaif Artist</h1>
                    <p class="bio-text">Designing interfaces.</p>
                    <p class="masthead-profile-locality"><a href="/places/lagos">Lagos, Nigeria</a></p>
                    <span>12.4k</span><span class="meta">followers</span>
                    <span>250</span><span class="meta">following</span>
                    <span>Member since Jan 2020</span>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'dribbble',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://dribbble.com/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Artist', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Designing interfaces.', $result->metadata['bio']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame(12400, $result->metadata['followers']);
        $this->assertSame(250, $result->metadata['following']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertStringContainsString('2020', (string) $result->metadata['created_at']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_instagram_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'user' => [
                        'username' => 'kaifcodec',
                        'full_name' => 'Kaif Gram',
                        'id' => '998877',
                        'profile_pic_url_hd' => 'https://instagram.example/avatar.jpg',
                        'biography' => 'Building in public.',
                        'business_email' => 'kaif@example.com',
                        'external_url' => 'https://kaif.dev',
                        'fbid' => '112233',
                        'is_business_account' => true,
                        'is_joined_recently' => false,
                        'is_private' => false,
                        'is_verified' => true,
                        'edge_followed_by' => ['count' => 54321],
                        'edge_follow' => ['count' => 123],
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'instagram',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.instagram.com/kaifcodec/', $result->profileUrl);
        $this->assertSame('Kaif Gram', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('998877', $result->metadata['instagram_id']);
        $this->assertSame('https://instagram.example/avatar.jpg', $result->metadata['avatar_url']);
        $this->assertSame('Building in public.', $result->metadata['bio']);
        $this->assertSame('kaif@example.com', $result->metadata['public_email']);
        $this->assertSame('https://kaif.dev', $result->metadata['website_url']);
        $this->assertSame('112233', $result->metadata['facebook_uid']);
        $this->assertTrue((bool) $result->metadata['is_business']);
        $this->assertFalse((bool) $result->metadata['is_joined_recently']);
        $this->assertFalse((bool) $result->metadata['is_private']);
        $this->assertTrue((bool) $result->metadata['is_verified']);
        $this->assertSame(54321, $result->metadata['followers']);
        $this->assertSame(123, $result->metadata['following']);
        $this->assertContains('https://kaif.dev', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_soundcloud_profile_extracts_hydration_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <meta property="og:title" content="SoundCloud">
                    <meta property="og:description" content="Music and audio">
                    <meta property="og:image" content="https://i1.sndcdn.com/avatars-soundcloud.jpg">
                    <script>
                    window.__sc_hydration = [{"hydratable":"user","data":{"id":12345,"username":"soundcloud","full_name":"SoundCloud","city":"Berlin","country_code":"DE","description":"Open audio platform.","followers_count":999,"followings_count":42,"track_count":77,"playlist_count":12,"likes_count":8,"avatar_url":"https://i1.sndcdn.com/avatars-soundcloud.jpg","verified":true}}];
                    </script>
                </head>
                <body>soundcloud://users:soundcloud</body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'soundcloud',
            target: 'soundcloud',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://soundcloud.com/soundcloud', $result->profileUrl);
        $this->assertSame('SoundCloud', $result->metadata['display_name']);
        $this->assertSame('soundcloud', $result->metadata['username']);
        $this->assertSame('Berlin, DE', $result->metadata['location']);
        $this->assertSame('Open audio platform.', $result->metadata['bio']);
        $this->assertSame(999, $result->metadata['followers']);
        $this->assertSame(42, $result->metadata['following']);
        $this->assertSame(77, $result->metadata['posts_count']);
        $this->assertSame(12, $result->metadata['playlist_count']);
        $this->assertSame(8, $result->metadata['likes_count']);
        $this->assertTrue((bool) $result->metadata['is_verified']);
        $this->assertContains('html_hydration', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_mixcloud_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'name' => 'Mixcloud',
                'follower_count' => 111,
                'following_count' => 22,
                'pictures' => [
                    'large' => 'https://thumbnailer.mixcloud.com/profile-large.jpg',
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'mixcloud',
            target: 'mixcloud',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.mixcloud.com/mixcloud/', $result->profileUrl);
        $this->assertSame('Mixcloud', $result->metadata['display_name']);
        $this->assertSame('mixcloud', $result->metadata['username']);
        $this->assertSame(111, $result->metadata['followers']);
        $this->assertSame(22, $result->metadata['following']);
        $this->assertSame('https://thumbnailer.mixcloud.com/profile-large.jpg', $result->metadata['avatar_url']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_lichess_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'username' => 'thibault',
                'profile' => [
                    'realName' => 'Thibault Duplessis',
                    'bio' => 'Founder of lichess.',
                    'links' => 'https://lichess.org',
                ],
                'streamer' => [
                    'twitch' => ['channel' => 'https://twitch.tv/lichessdotorg'],
                    'youtube' => ['channel' => 'https://youtube.com/@lichessdotorg'],
                ],
                'patron' => true,
                'verified' => true,
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'lichess',
            target: 'thibault',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://lichess.org/@/thibault', $result->profileUrl);
        $this->assertSame('Thibault Duplessis', $result->metadata['display_name']);
        $this->assertSame('thibault', $result->metadata['username']);
        $this->assertSame('Founder of lichess.', $result->metadata['bio']);
        $this->assertTrue((bool) $result->metadata['is_verified']);
        $this->assertTrue((bool) $result->metadata['patron']);
        $this->assertContains('https://lichess.org', $result->metadata['external_links']);
        $this->assertContains('https://twitch.tv/lichessdotorg', $result->metadata['external_links']);
        $this->assertContains('https://youtube.com/@lichessdotorg', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_osu_profile_extracts_metadata_from_primary_html(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <meta property="og:title" content="peppy · player info">
                    <meta property="og:description" content="Rank (osu): Global #829,925 | Country #17,171">
                    <meta property="og:image" content="https://a.ppy.sh/2.png">
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
            validatorKey: 'osu',
            target: 'peppy',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://osu.ppy.sh/users/peppy', $result->profileUrl);
        $this->assertSame('peppy', $result->metadata['display_name']);
        $this->assertSame('peppy', $result->metadata['username']);
        $this->assertSame('https://a.ppy.sh/2.png', $result->metadata['avatar_url']);
        $this->assertSame('Rank (osu): Global #829,925 | Country #17,171', $result->metadata['rank_summary']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_chess_com_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'username' => 'hikaru',
                'name' => 'Hikaru Nakamura',
                'title' => 'GM',
                'status' => 'premium',
                'league' => 'legend',
                'location' => 'United States',
                'followers' => 123456,
                'avatar' => 'https://images.chesscomfiles.com/uploads/v1/user/15448422.12345678.200x200o.jpg',
                'twitch_url' => 'https://www.twitch.tv/gmhikaru',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'chess_com',
            target: 'hikaru',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.chess.com/member/hikaru', $result->profileUrl);
        $this->assertSame('Hikaru Nakamura', $result->metadata['display_name']);
        $this->assertSame('hikaru', $result->metadata['username']);
        $this->assertSame('GM', $result->metadata['title']);
        $this->assertSame('premium', $result->metadata['status']);
        $this->assertSame('legend', $result->metadata['league']);
        $this->assertSame('United States', $result->metadata['location']);
        $this->assertSame(123456, $result->metadata['followers']);
        $this->assertSame('https://images.chesscomfiles.com/uploads/v1/user/15448422.12345678.200x200o.jpg', $result->metadata['avatar_url']);
        $this->assertContains('https://www.twitch.tv/gmhikaru', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_speedrun_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'id' => 'o86n592j',
                    'names' => [
                        'international' => 'Darbian',
                    ],
                    'role' => 'user',
                    'signup' => '2014-11-25T13:42:00Z',
                    'location' => [
                        'country' => [
                            'names' => ['international' => 'United States'],
                        ],
                        'region' => [
                            'names' => ['international' => 'California'],
                        ],
                    ],
                    'twitch' => [
                        'uri' => 'https://www.twitch.tv/darbian',
                    ],
                    'youtube' => [
                        'uri' => 'https://www.youtube.com/@darbian',
                    ],
                    'twitter' => [
                        'uri' => 'https://x.com/darbian',
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'speedrun',
            target: 'darbian',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.speedrun.com/users/darbian', $result->profileUrl);
        $this->assertSame('Darbian', $result->metadata['display_name']);
        $this->assertSame('darbian', $result->metadata['username']);
        $this->assertSame('o86n592j', $result->metadata['speedrun_id']);
        $this->assertSame('user', $result->metadata['role']);
        $this->assertSame('California, United States', $result->metadata['location']);
        $this->assertSame('2014-11-25T13:42:00+00:00', $result->metadata['created_at']);
        $this->assertContains('https://www.twitch.tv/darbian', $result->metadata['external_links']);
        $this->assertContains('https://www.youtube.com/@darbian', $result->metadata['external_links']);
        $this->assertContains('https://x.com/darbian', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_warpcast_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'result' => [
                    'user' => [
                        'fid' => 3,
                        'displayName' => 'dwr',
                        'accountLevel' => 'power',
                        'followerCount' => 1000,
                        'followingCount' => 250,
                        'profile' => [
                            'bio' => ['text' => 'testing warpcast'],
                            'location' => ['description' => 'San Francisco'],
                        ],
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'warpcast',
            target: 'dwr',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://warpcast.com/dwr', $result->profileUrl);
        $this->assertSame('dwr', $result->metadata['display_name']);
        $this->assertSame('dwr', $result->metadata['username']);
        $this->assertSame(3, $result->metadata['fid']);
        $this->assertSame('power', $result->metadata['account_level']);
        $this->assertSame('testing warpcast', $result->metadata['bio']);
        $this->assertSame('San Francisco', $result->metadata['location']);
        $this->assertSame(1000, $result->metadata['followers']);
        $this->assertSame(250, $result->metadata['following']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_archwiki_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'query' => [
                    'users' => [[
                        'userid' => 42,
                        'registration' => '2010-01-02T03:04:05Z',
                        'editcount' => 1234,
                        'gender' => 'male',
                        'groups' => ['*', 'user', 'autoconfirmed'],
                    ]],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'archwiki',
            target: 'Alad',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://wiki.archlinux.org/title/User:Alad', $result->profileUrl);
        $this->assertSame('Alad', $result->metadata['username']);
        $this->assertSame(42, $result->metadata['archwiki_user_id']);
        $this->assertSame(1234, $result->metadata['edit_count']);
        $this->assertSame('male', $result->metadata['gender']);
        $this->assertSame('2010-01-02T03:04:05+00:00', $result->metadata['created_at']);
        $this->assertSame(['user', 'autoconfirmed'], $result->metadata['groups']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_etoro_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'gcid' => '123',
                'firstName' => 'Yoni',
                'lastName' => 'Assia',
                'aboutMe' => 'CEO of eToro',
                'avatars' => [
                    ['url' => 'https://etoro-cdn/avatar.jpg'],
                ],
                'isVerified' => true,
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'etoro',
            target: 'yoniassia',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.etoro.com/people/yoniassia', $result->profileUrl);
        $this->assertSame('Yoni Assia', $result->metadata['display_name']);
        $this->assertSame('yoniassia', $result->metadata['username']);
        $this->assertSame('CEO of eToro', $result->metadata['bio']);
        $this->assertSame('https://etoro-cdn/avatar.jpg', $result->metadata['avatar_url']);
        $this->assertTrue((bool) $result->metadata['is_verified']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_kick_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 98765,
                'slug' => 'xqc',
                'is_banned' => false,
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'kick',
            target: 'xqc',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://kick.com/xqc', $result->profileUrl);
        $this->assertSame('xqc', $result->metadata['username']);
        $this->assertSame(98765, $result->metadata['kick_id']);
        $this->assertSame('xqc', $result->metadata['slug']);
        $this->assertFalse((bool) $result->metadata['is_banned']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_minecraft_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => '069a79f444e94726a5befca90e38aaf5',
                'name' => 'Notch',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'minecraft',
            target: 'Notch',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://namemc.com/profile/Notch', $result->profileUrl);
        $this->assertSame('Notch', $result->metadata['display_name']);
        $this->assertSame('Notch', $result->metadata['username']);
        $this->assertSame('069a79f444e94726a5befca90e38aaf5', $result->metadata['uuid']);
        $this->assertSame('https://crafatar.com/avatars/069a79f444e94726a5befca90e38aaf5?size=256&overlay', $result->metadata['avatar_url']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_roblox_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://users.roblox.com/v1/usernames/users') {
                $this->assertSame('POST', $request->method());
                $this->assertTrue($request->hasHeader('Content-Type', 'application/json'));
                $this->assertSame([
                    'usernames' => ['Builderman'],
                    'excludeBannedUsers' => false,
                ], $request->data());

                return Http::response([
                    'data' => [[
                        'id' => 156,
                        'displayName' => 'Builderman',
                        'hasVerifiedBadge' => true,
                    ]],
                ], 200, ['Content-Type' => 'application/json']);
            }

            if ($request->url() === 'https://users.roblox.com/v1/users/156') {
                return Http::response([
                    'description' => 'Welcome to Roblox!',
                    'created' => '2006-02-27T21:06:24.06Z',
                    'isBanned' => false,
                ], 200, ['Content-Type' => 'application/json']);
            }

            return Http::response([], 404, ['Content-Type' => 'application/json']);
        });

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'roblox',
            target: 'Builderman',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.roblox.com/users/156/profile', $result->profileUrl);
        $this->assertSame('Builderman', $result->metadata['display_name']);
        $this->assertSame('Builderman', $result->metadata['username']);
        $this->assertSame(156, $result->metadata['roblox_user_id']);
        $this->assertTrue((bool) $result->metadata['is_verified']);
        $this->assertSame('Welcome to Roblox!', $result->metadata['bio']);
        $this->assertSame('2006-02-27T21:06:24+00:00', $result->metadata['created_at']);
        $this->assertFalse((bool) $result->metadata['is_banned']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_x_vxtwitter_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            'https://api.vxtwitter.com/*' => Http::response([
                'name' => 'OpenAI',
                'description' => 'AI research and deployment company.',
                'location' => 'San Francisco, CA',
                'created_at' => '2015-12-11T01:13:48Z',
                'followers_count' => 4200000,
                'following_count' => 12,
                'profile_image_url' => 'https://pbs.twimg.com/profile_images/openai_normal.jpg',
                'protected' => false,
                'tweet_count' => 12345,
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'x',
            target: 'openai',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://x.com/openai', $result->profileUrl);
        $this->assertSame('OpenAI', $result->metadata['display_name']);
        $this->assertSame('openai', $result->metadata['username']);
        $this->assertSame('AI research and deployment company.', $result->metadata['bio']);
        $this->assertSame('San Francisco, CA', $result->metadata['location']);
        $this->assertSame('2015-12-11T01:13:48+00:00', $result->metadata['created_at']);
        $this->assertSame(4200000, $result->metadata['followers']);
        $this->assertSame(12, $result->metadata['following']);
        $this->assertSame(12345, $result->metadata['posts_count']);
        $this->assertSame('https://pbs.twimg.com/profile_images/openai.jpg', $result->metadata['avatar_url']);
        $this->assertFalse((bool) $result->metadata['is_private']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_launchpad_200_profile_page_is_taken(): void
    {
        Http::fake([
            '*' => Http::response('<html><head><title>Matthew Paul Thomas in Launchpad</title></head><body></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = (new LaunchpadValidator())->check('mpt');

        $this->assertSame('Taken', $result->status);
    }

    public function test_hackernews_profile_extracts_metadata_from_primary_html(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <link rel="canonical" href="https://news.ycombinator.com/user?id=pg">
                    <title>Profile: pg | Hacker News</title>
                </head>
                <body>
                    <table>
                        <tr><td valign="top">user:</td><td><a href="user?id=pg" class="hnuser">pg</a></td></tr>
                        <tr><td valign="top">created:</td><td>October 9, 2006</td></tr>
                        <tr><td valign="top">karma:</td><td>157316</td></tr>
                        <tr><td valign="top">about:</td><td>Bug fixer. <a href="https://www.paulgraham.com/">Website</a></td></tr>
                    </table>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'hackernews',
            target: 'pg',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://news.ycombinator.com/user?id=pg', $result->profileUrl);
        $this->assertSame('pg', $result->metadata['display_name']);
        $this->assertSame('Bug fixer. Website', $result->metadata['bio']);
        $this->assertSame('2006-10-09T00:00:00+00:00', $result->metadata['created_at']);
        $this->assertSame(157316, $result->metadata['karma']);
        $this->assertContains('https://www.paulgraham.com/', $result->metadata['external_links']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_35photo_available_page_is_not_misclassified_by_recaptcha_footer_text(): void
    {
        Http::fake([
            '*' => Http::response('<html><body>Catalogs of professional author This site is protected by reCAPTCHA and the Google Privacy Policy</body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = (new Site35photoValidator())->check('hienyimba');

        $this->assertSame('Available', $result->status);
    }

    public function test_35photo_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <body>
                    <h1 class="thinFont">Kaif Lens</h1>
                    <span title="Photographers from Nigeria"></span>
                    <span title="Photographers from the city of Lagos"></span>
                    <span title="Total photos see all"><span style="font-size:2.6em">87</span></span>
                    <img class="avatar140" src="//images.35photo.pro/avatar.jpg">
                    <span title="Total photos"></span>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: '35photo',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://35photo.pro/@kaifcodec/', $result->profileUrl);
        $this->assertSame('Kaif Lens', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame(87, $result->metadata['posts_count']);
        $this->assertSame(87, $result->metadata['photos']);
        $this->assertSame('https://images.35photo.pro/avatar.jpg', $result->metadata['avatar_url']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_goodreads_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <title>Kaif Codec's books | Goodreads</title>
                    <meta property="og:title" content="Kaif Codec (Author)">
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
            validatorKey: 'goodreads',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.goodreads.com/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame("Kaif Codec's books | Goodreads", $result->metadata['profile_title']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_packagist_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head><title>Kaif Codec - Packagist</title></head>
                <body>
                    <a href="/packages/kaifcodec/user-scanner">user-scanner</a>
                    <a href="/packages/kaifcodec/metadata-tools">metadata-tools</a>
                    <a href="/packages/kaifcodec/user-scanner">user-scanner duplicate</a>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'packagist',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://packagist.org/users/kaifcodec/', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame(2, $result->metadata['packages_count']);
        $this->assertSame(2, $result->metadata['posts_count']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_linktree_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html>
                <head>
                    <title>Kaif Codec | Linktree</title>
                </head>
                <body>
                    <script id="__NEXT_DATA__" type="application/json">
                    {"props":{"pageProps":{"pageTitle":"Kaif Codec","description":"Links for everything I build.","isProfileVerified":true,"links":[{"title":"Site","url":"https://kaif.dev"},{"title":"Docs","url":"https://docs.kaif.dev"}],"socialLinks":[{"platform":"github","url":"https://github.com/kaifcodec"}],"account":{"profilePictureUrl":"https://cdn.linktr.ee/avatar.png"}}}}
                    </script>
                </body>
                </html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'linktree',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://linktr.ee/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Links for everything I build.', $result->metadata['bio']);
        $this->assertSame('https://cdn.linktr.ee/avatar.png', $result->metadata['avatar_url']);
        $this->assertTrue((bool) $result->metadata['is_verified']);
        $this->assertContains('https://kaif.dev', $result->metadata['external_links']);
        $this->assertContains('https://github.com/kaifcodec', $result->metadata['external_links']);
        $this->assertContains('profile_html', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_bluesky_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            'https://bsky.social/xrpc/com.atproto.temp.checkHandleAvailability*' => Http::response([
                'result' => [
                    '$type' => 'com.atproto.temp.checkHandleAvailability#resultUnavailable',
                ],
            ], 200, ['Content-Type' => 'application/json']),
            'https://public.api.bsky.app/xrpc/app.bsky.actor.getProfile*' => Http::response([
                'handle' => 'kaifcodec.bsky.social',
                'displayName' => 'Kaif Codec',
                'description' => 'Posting build notes.',
                'followersCount' => 101,
                'followsCount' => 55,
                'postsCount' => 78,
                'avatar' => 'https://cdn.bsky.app/avatar.png',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'bluesky',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://bsky.app/profile/kaifcodec.bsky.social', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('kaifcodec.bsky.social', $result->metadata['handle']);
        $this->assertSame('Posting build notes.', $result->metadata['bio']);
        $this->assertSame(101, $result->metadata['followers']);
        $this->assertSame(55, $result->metadata['following']);
        $this->assertSame(78, $result->metadata['posts_count']);
        $this->assertSame('https://cdn.bsky.app/avatar.png', $result->metadata['avatar_url']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_devto_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'name' => 'Kaif Codec',
                'summary' => 'Shipping developer tools.',
                'location' => 'Lagos, Nigeria',
                'joined_at' => 'May 10, 2021',
                'website_url' => 'https://kaif.dev',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'devto',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://dev.to/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Shipping developer tools.', $result->metadata['bio']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame('https://kaif.dev', $result->metadata['website_url']);
        $this->assertContains('https://kaif.dev', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_gitlab_api_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([[
                'id' => 4242,
                'name' => 'Kaif Codec',
                'username' => 'kaifcodec',
                'state' => 'active',
                'avatar_url' => 'https://secure.gravatar.com/avatar/abcdef1234567890abcdef1234567890?s=80&d=identicon',
            ]], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'gitlab',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://gitlab.com/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame(4242, $result->metadata['gitlab_id']);
        $this->assertSame('active', $result->metadata['account_state']);
        $this->assertSame('https://secure.gravatar.com/avatar/abcdef1234567890abcdef1234567890?s=80&d=identicon', $result->metadata['avatar_url']);
        $this->assertSame('https://gravatar.com/abcdef1234567890abcdef1234567890', $result->metadata['gravatar_url']);
        $this->assertSame('kaifcodec', $result->metadata['gravatar_username']);
        $this->assertSame('abcdef1234567890abcdef1234567890', $result->metadata['gravatar_email_md5_hash']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_habr_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html><body>{"authorRefs":{"user-1":{"fullname":"Kaif Codec","speciality":"Backend Engineer","rating":42.5,"scoreStats":{"score":133.7},"followStats":{"followersCount":1200,"followCount":88},"counterStats":{"postCount":45,"commentCount":321},"registerDateTime":"2020-01-02T03:04:05+00:00","avatarUrl":"https://habrastorage.org/avatar.png"}},"authorIds":[]}</body></html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'habr',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://habr.com/ru/users/kaifcodec/', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Backend Engineer', $result->metadata['speciality']);
        $this->assertSame(42.5, $result->metadata['rating']);
        $this->assertSame(133.7, $result->metadata['karma']);
        $this->assertSame(1200, $result->metadata['followers']);
        $this->assertSame(88, $result->metadata['following']);
        $this->assertSame(45, $result->metadata['posts_count']);
        $this->assertSame(321, $result->metadata['comments_count']);
        $this->assertSame('2020-01-02T03:04:05+00:00', $result->metadata['created_at']);
        $this->assertSame('https://habrastorage.org/avatar.png', $result->metadata['avatar_url']);
        $this->assertContains('html_hydration', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_flickr_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html><body>modelExport:{"person":{"_flickrModelRegistry":"person-models","id":"5151","pathAlias":"kaifcodec","username":"KaifNick","realname":"Kaif Codec","buddyicon":{"data":{"retina":"//live.staticflickr.com/avatar.jpg"}},"isPro":true},"profile":{"_flickrModelRegistry":"person-profile-models","location":"Lagos, Nigeria","photoCount":87},"contacts":{"_flickrModelRegistry":"person-contacts-count-models","followerCount":910,"followingCount":44}},auth</body></html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'flickr',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.flickr.com/photos/kaifcodec/', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame(5151, $result->metadata['flickr_id']);
        $this->assertSame('kaifcodec', $result->metadata['flickr_username']);
        $this->assertSame('KaifNick', $result->metadata['flickr_nickname']);
        $this->assertSame('https://live.staticflickr.com/avatar.jpg', $result->metadata['avatar_url']);
        $this->assertTrue((bool) $result->metadata['is_pro']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame(87, $result->metadata['posts_count']);
        $this->assertSame(87, $result->metadata['photos_count']);
        $this->assertSame(910, $result->metadata['followers']);
        $this->assertSame(44, $result->metadata['following']);
        $this->assertContains('html_hydration', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_flickr_502_error_page_is_reported_as_backend_error_instead_of_generic_parse_error(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <!DOCTYPE html>
                <html>
                <head><title>Flickr</title></head>
                <body style="background-image:url('/flickr_panda_error_pages/bg_error_hold_your_clicks.jpg')">
                    Sorry, Flickr is having trouble.
                </body>
                </html>
                HTML,
                502,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = (new \App\Services\Scanner\Validators\Generated\User\FlickrValidator())->check('flickr');

        $this->assertSame('Error', $result->status);
        $this->assertSame('flickr: backend error page (HTTP 502)', $result->reason);
    }

    public function test_bandcamp_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response(
                <<<'HTML'
                <html><head><title>Kaif collection | Bandcamp</title></head><body><div data-blob="{&quot;fan_data&quot;:{&quot;fan_id&quot;:606,&quot;name&quot;:&quot;Kaif Codec&quot;,&quot;location&quot;:&quot;Lagos, Nigeria&quot;,&quot;bio&quot;:&quot;Collector of sounds.&quot;,&quot;website_url&quot;:&quot;https://kaif.dev&quot;,&quot;followers_count&quot;:321,&quot;following_bands_count&quot;:11,&quot;following_fans_count&quot;:7,&quot;fav_genre&quot;:&quot;Ambient&quot;,&quot;photo&quot;:{&quot;image_id&quot;:1234567890}}}"></div></body></html>
                HTML,
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'bandcamp',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://bandcamp.com/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame(606, $result->metadata['bandcamp_id']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame('Collector of sounds.', $result->metadata['bio']);
        $this->assertSame('https://kaif.dev', $result->metadata['website_url']);
        $this->assertSame(321, $result->metadata['followers']);
        $this->assertSame(11, $result->metadata['following_bands']);
        $this->assertSame(7, $result->metadata['following_fans']);
        $this->assertSame(18, $result->metadata['following']);
        $this->assertSame('Ambient', $result->metadata['fav_genre']);
        $this->assertSame('https://f4.bcbits.com/img/001234567890_10.jpg', $result->metadata['avatar_url']);
        $this->assertContains('https://kaif.dev', $result->metadata['external_links']);
        $this->assertContains('html_hydration', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_discogs_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 777,
                'name' => 'Kaif Codec',
                'location' => 'Lagos, Nigeria',
                'profile' => 'Digging for rare records.',
                'registered' => '2021-06-07T08:09:10-00:00',
                'releases_contributed' => 54,
                'releases_rated' => 80,
                'num_lists' => 6,
                'num_collection' => 200,
                'num_wantlist' => 33,
                'home_page' => 'https://kaif.dev',
                'avatar_url' => 'https://img.discogs.com/avatar.jpg',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'discogs',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://www.discogs.com/user/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame(777, $result->metadata['discogs_id']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame('Digging for rare records.', $result->metadata['bio']);
        $this->assertSame('2021-06-07T08:09:10+00:00', $result->metadata['created_at']);
        $this->assertSame('https://kaif.dev', $result->metadata['website_url']);
        $this->assertSame('https://img.discogs.com/avatar.jpg', $result->metadata['avatar_url']);
        $this->assertSame(54, $result->metadata['releases_contributed']);
        $this->assertSame(80, $result->metadata['releases_rated']);
        $this->assertSame(6, $result->metadata['lists']);
        $this->assertSame(200, $result->metadata['collection_items']);
        $this->assertSame(33, $result->metadata['wantlist_items']);
        $this->assertContains('https://kaif.dev', $result->metadata['external_links']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_pypi_xmlrpc_profile_is_taken_and_preserves_primary_metadata(): void
    {
        Http::fake([
            'https://pypi.org/pypi' => Http::response(
                <<<'XML'
                <?xml version="1.0"?>
                <methodResponse>
                  <params>
                    <param>
                      <value>
                        <array>
                          <data>
                            <value>
                              <array>
                                <data>
                                  <value><string>Owner</string></value>
                                  <value><string>user-scanner</string></value>
                                </data>
                              </array>
                            </value>
                            <value>
                              <array>
                                <data>
                                  <value><string>Owner</string></value>
                                  <value><string>metadata-tools</string></value>
                                </data>
                              </array>
                            </value>
                          </data>
                        </array>
                      </value>
                    </param>
                  </params>
                </methodResponse>
                XML,
                200,
                ['Content-Type' => 'text/xml']
            ),
            'https://pypi.org/pypi/user-scanner/json' => Http::response([
                'info' => [
                    'author' => 'Kaif Codec',
                    'author_email' => 'Kaif Codec <kaif@example.com>',
                    'maintainer' => null,
                    'maintainer_email' => null,
                ],
            ], 200, ['Content-Type' => 'application/json']),
            'https://pypi.org/pypi/metadata-tools/json' => Http::response([
                'info' => [
                    'author' => null,
                    'author_email' => null,
                    'maintainer' => null,
                    'maintainer_email' => null,
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: 'pypi',
            target: 'kaifcodec',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Found', $result->status);
        $this->assertSame('https://pypi.org/user/kaifcodec', $result->profileUrl);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('kaif@example.com', $result->metadata['public_email']);
        $this->assertSame(2, $result->metadata['packages_count']);
        $this->assertSame(2, $result->metadata['posts_count']);
        $this->assertSame(['user-scanner', 'metadata-tools'], $result->metadata['packages']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_apple_developer_security_verification_page_is_reported_as_antibot_challenge(): void
    {
        Http::fake([
            '*' => Http::response(
                '<html><head><title>Security Verification</title></head><body>Security verification page to protect against malicious bots.</body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = (new AppledeveloperValidator())->check('hienyimba');

        $this->assertSame('Error', $result->status);
        $this->assertStringContainsString('anti-bot challenge detected', $result->reason);
    }

    public function test_fiverr_403_is_reported_as_blocked_instead_of_generic_parse_error(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Request blocked by security policy.'], 403, ['Content-Type' => 'application/json']),
        ]);

        $result = (new FiverrValidator())->check('hienyimba');

        $this->assertSame('Error', $result->status);
        $this->assertStringContainsString('blocked/rate-limited', $result->reason);
    }

    /**
     * @return array<string, array{0:string,1:int,2:string}>
     */
    public static function generatedStatusValidatorProvider(): array
    {
        return [
            'academia-404' => [AcademiaValidator::class, 404, 'Available'],
            'amazon-404' => [AmazonValidator::class, 404, 'Available'],
        ];
    }

    /**
     * @return array<string, array{0:string,1:int,2:array<string, mixed>|string}>
     */
    public static function manualAvailabilityProvider(): array
    {
        return [
            'codeforces-400' => [CodeforcesValidator::class, 400, ['status' => 'FAILED', 'comment' => 'handles: User with handle hienyimba not found']],
            'fansly-empty-response' => [FanslyValidator::class, 200, ['success' => true, 'response' => []]],
            'niftygateway-400' => [NiftygatewayValidator::class, 400, ['didSucceed' => false, 'errorType' => 'not_found']],
            'duolingo-empty-users' => [DuolingoValidator::class, 200, ['users' => []]],
            'freelancer-empty-users' => [FreelancerValidator::class, 200, ['status' => 'success', 'result' => ['users' => []]]],
            '500px-not-found-error' => [Px500Validator::class, 200, ['errors' => [['extensions' => ['response' => ['status' => 404]]]], 'data' => ['userByUsername' => null]]],
        ];
    }

    /**
     * @return array<string, array{0:string,1:string,2:string}>
     */
    public static function discourseStyleEmptyUserProvider(): array
    {
        return [
            'discourse-meta-empty-user-is-error' => [DiscourseMetaValidator::class, 'Error', 'Unexpected response status: 200'],
            'f-droid-empty-user-is-error' => [FDroidValidator::class, 'Error', 'Unexpected response status: 200'],
            'elixir-forum-empty-user-is-available' => [ElixirForumValidator::class, 'Available', ''],
            'jupyter-forum-empty-user-is-available' => [JupyterForumValidator::class, 'Available', ''],
        ];
    }
}
