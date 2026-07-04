<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Scanner\Validators\Generated\User\AcademiaValidator;
use App\Services\Scanner\Validators\Generated\User\AmazonValidator;
use App\Services\Scanner\Validators\Generated\User\AppledeveloperValidator;
use App\Services\Scanner\Validators\Generated\User\CodecademyValidator;
use App\Services\Scanner\Validators\Generated\User\CodeforcesValidator;
use App\Services\Scanner\Validators\Generated\User\DuolingoValidator;
use App\Services\Scanner\Validators\Generated\User\FanslyValidator;
use App\Services\Scanner\Validators\Generated\User\FiverrValidator;
use App\Services\Scanner\Validators\Generated\User\FreelancerValidator;
use App\Services\Scanner\Validators\Generated\User\InstructablesValidator;
use App\Services\Scanner\Validators\Generated\User\KickValidator;
use App\Services\Scanner\Validators\Generated\User\NiftygatewayValidator;
use App\Services\Scanner\Validators\Generated\User\PackagistValidator;
use App\Services\Scanner\Validators\Generated\User\Px500Validator;
use App\Services\Scanner\Validators\Generated\User\PypiValidator;
use App\Services\Scanner\Validators\Generated\User\Site35photoValidator;
use App\Services\Scanner\Validators\Generated\User\TumblrValidator;
use App\Services\Scanner\Validators\Generated\User\WikipediaValidator;
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

    public function test_kick_json_200_response_is_taken(): void
    {
        Http::fake([
            '*' => Http::response(['id' => 123, 'slug' => 'hienyimba', 'is_banned' => false], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = (new KickValidator())->check('hienyimba');

        $this->assertSame('Taken', $result->status);
    }

    public function test_35photo_available_page_is_not_misclassified_by_recaptcha_footer_text(): void
    {
        Http::fake([
            '*' => Http::response('<html><body>Catalogs of professional author This site is protected by reCAPTCHA and the Google Privacy Policy</body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = (new Site35photoValidator())->check('hienyimba');

        $this->assertSame('Available', $result->status);
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
}
