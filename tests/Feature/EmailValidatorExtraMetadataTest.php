<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Scanner\Validators\Generated\Email\AdobeValidator;
use App\Services\Scanner\Validators\Generated\Email\CourseraValidator;
use App\Services\Scanner\Validators\Generated\Email\EtsyValidator;
use App\Services\Scanner\Validators\Generated\Email\WalmartValidator;
use App\Services\Scanner\Validators\Generated\Email\WixValidator;
use App\Services\Scanner\Validators\Generated\Manual\Email\OtterEmailValidator;
use App\Services\Scanner\Validators\Generated\Manual\Email\SamsclubEmailValidator;
use App\Services\Scanner\ScannerEngineService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class EmailValidatorExtraMetadataTest extends TestCase
{
    public function test_adobe_registered_result_includes_authentication_metadata(): void
    {
        Http::fake([
            'https://auth.services.adobe.com/signin/v2/users/accounts' => Http::response([
                [
                    'accountType' => 'AdobeID',
                    'authenticationMethods' => [
                        ['id' => 'password'],
                        ['id' => 'google'],
                    ],
                ],
            ], 200),
        ]);

        $result = (new AdobeValidator())->check('jane@example.com');

        $this->assertSame('Registered', $result->status);
        $this->assertSame("Authentication methods: password, google\nAccount types: AdobeID", $result->extra);
    }

    public function test_coursera_registered_result_includes_login_methods_metadata(): void
    {
        Http::fake([
            'https://www.coursera.org/api/userAccounts.v1*' => Http::response([
                'loginMethods' => ['password', 'google'],
            ], 200),
        ]);

        $result = (new CourseraValidator())->check('jane@example.com');

        $this->assertSame('Registered', $result->status);
        $this->assertSame('Login methods: password, google', $result->extra);
    }

    public function test_etsy_registered_result_includes_user_id_metadata(): void
    {
        Http::fake([
            'https://www.etsy.com/api/v3/ajax/public/users/by-identity-optional*' => Http::response([
                'user_id' => 12345,
            ], 200),
        ]);

        $result = (new EtsyValidator())->check('jane@example.com');

        $this->assertSame('Registered', $result->status);
        $this->assertSame('User ID: 12345', $result->extra);
    }

    public function test_walmart_registered_result_includes_account_metadata(): void
    {
        Http::fake([
            'https://identity.walmart.com/account/login*' => Http::response('', 200),
            'https://identity.walmart.com/orchestra/idp/graphql' => Http::response([
                'data' => [
                    'getLoginOptions' => [
                        'loginOptions' => [
                            'signInPreference' => 'PASSWORD',
                            'loginMaskedEmailId' => 'j***@example.com',
                            'maskedPhoneNumberDetails' => [
                                'loginPhoneLastFour' => '4321',
                            ],
                            'hasPasskeyOnProfile' => true,
                            'accountDomain' => 'example.com',
                            'residencyRegion' => [
                                'residencyCountryCode' => 'US',
                            ],
                        ],
                        'errors' => [],
                    ],
                ],
            ], 200),
        ]);

        $result = (new WalmartValidator())->check('jane@example.com');

        $this->assertSame('Registered', $result->status);
        $this->assertSame(
            "Sign-in preference: PASSWORD\nMasked email: j***@example.com\nMasked phone: ***4321\nPasskey on profile: Yes\nAccount domain: example.com\nResidency country: US",
            $result->extra
        );
    }

    public function test_wix_registered_result_includes_account_match_metadata(): void
    {
        Http::fake([
            'https://users.wix.com/wix-users/v1/userAccountsByEmail' => Http::response([
                'accountsData' => [
                    [
                        'provider' => 'google',
                        'accountType' => 'member',
                    ],
                    [
                        'provider' => 'password',
                        'accountType' => 'member',
                    ],
                ],
            ], 200),
        ]);

        $result = (new WixValidator())->check('jane@example.com');

        $this->assertSame('Registered', $result->status);
        $this->assertSame("Accounts matched: 2\nProviders: google, password\nAccount types: member", $result->extra);
    }

    public function test_otter_registered_result_includes_workspace_metadata(): void
    {
        Http::fake([
            'https://otter.ai/forward/api/v1/login_csrf' => Http::response([
                'status' => 'OK',
                'logged-in' => false,
            ], 200, [
                'Set-Cookie' => 'csrftoken=test-csrf-token; Path=/; Secure',
            ]),
            'https://otter.ai/forward/api/v1/check_email*' => Http::response([
                'status' => 'OK',
                'user_email' => true,
                'email_verified' => true,
                'email_host' => 'google',
                'is_personal' => true,
                'domain_label' => 'personal',
                'workspace' => [
                    'name' => 'Izundu',
                    'id' => 16365582,
                    'is_pending_member' => false,
                    'sso_enabled' => null,
                    'sso_required' => false,
                    'handle' => 'izundu82',
                    'email' => 'jane@example.com',
                    'disable_sso_sandbox' => false,
                ],
            ], 200),
        ]);

        $result = (new OtterEmailValidator())->check('jane@example.com');

        $this->assertSame('Registered', $result->status);
        $this->assertSame(
            "Email verified: Yes\nEmail host: google\nPersonal domain: Yes\nDomain label: personal\nWorkspace ID: 16365582\nWorkspace name: Izundu\nWorkspace handle: izundu82\nPending member: No\nSSO required: No",
            $result->extra
        );
    }

    public function test_adobe_registered_result_exposes_structured_metadata(): void
    {
        Http::fake([
            'https://auth.services.adobe.com/signin/v2/users/accounts' => Http::response([
                [
                    'accountType' => 'AdobeID',
                    'authenticationMethods' => [
                        ['id' => 'password'],
                        ['id' => 'google'],
                    ],
                ],
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'adobe',
            target: 'jane@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('jane@example.com', $result->metadata['public_email']);
        $this->assertSame(['password', 'google'], $result->metadata['authentication_methods']);
        $this->assertSame(['AdobeID'], $result->metadata['account_types']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_coursera_registered_result_exposes_structured_metadata(): void
    {
        Http::fake([
            'https://www.coursera.org/api/userAccounts.v1*' => Http::response([
                'loginMethods' => ['password', 'google'],
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'coursera',
            target: 'jane@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('jane@example.com', $result->metadata['public_email']);
        $this->assertTrue((bool) $result->metadata['account_exists']);
        $this->assertSame(['password', 'google'], $result->metadata['login_methods']);
        $this->assertSame(['login_methods'], $result->metadata['sensitive_fields']);
        $this->assertContains('login_methods_api', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
        $this->assertSame(200, $result->metadata['http_status']);
        $this->assertArrayHasKey('latency_ms', $result->metadata);
        $this->assertSame(0.86, $result->confidence);
    }

    public function test_wix_registered_result_exposes_structured_metadata(): void
    {
        Http::fake([
            'https://users.wix.com/wix-users/v1/userAccountsByEmail' => Http::response([
                'accountsData' => [
                    [
                        'provider' => 'google',
                        'accountType' => 'member',
                    ],
                    [
                        'provider' => 'password',
                        'accountType' => 'member',
                    ],
                ],
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'wix',
            target: 'jane@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('jane@example.com', $result->metadata['public_email']);
        $this->assertSame(2, $result->metadata['accounts_matched']);
        $this->assertSame(['google', 'password'], $result->metadata['providers']);
        $this->assertSame(['member'], $result->metadata['account_types']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_otter_registered_result_exposes_structured_metadata(): void
    {
        Http::fake([
            'https://otter.ai/forward/api/v1/login_csrf' => Http::response([
                'status' => 'OK',
                'logged-in' => false,
            ], 200, [
                'Set-Cookie' => 'csrftoken=test-csrf-token; Path=/; Secure',
            ]),
            'https://otter.ai/forward/api/v1/check_email*' => Http::response([
                'status' => 'OK',
                'user_email' => true,
                'email_verified' => true,
                'email_host' => 'google',
                'is_personal' => true,
                'domain_label' => 'personal',
                'workspace' => [
                    'name' => 'Izundu',
                    'id' => 16365582,
                    'is_pending_member' => false,
                    'sso_enabled' => null,
                    'sso_required' => false,
                    'handle' => 'izundu82',
                    'email' => 'jane@example.com',
                    'disable_sso_sandbox' => false,
                ],
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'otter',
            target: 'jane@example.com',
            options: ['proxy' => 'http://user:pass@disp.oxylabs.io:8008'],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('jane@example.com', $result->metadata['public_email']);
        $this->assertTrue((bool) $result->metadata['account_exists']);
        $this->assertTrue((bool) $result->metadata['email_verified']);
        $this->assertSame('google', $result->metadata['email_host']);
        $this->assertTrue((bool) $result->metadata['is_personal']);
        $this->assertSame('personal', $result->metadata['domain_label']);
        $this->assertSame(16365582, $result->metadata['workspace_id']);
        $this->assertSame('Izundu', $result->metadata['workspace_name']);
        $this->assertSame('izundu82', $result->metadata['workspace_handle']);
        $this->assertFalse((bool) $result->metadata['is_pending_member']);
        $this->assertFalse((bool) $result->metadata['sso_required']);
        $this->assertFalse((bool) $result->metadata['disable_sso_sandbox']);
        $this->assertSame(['email_verified', 'email_host', 'sso_enabled', 'sso_required'], $result->metadata['sensitive_fields']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertContains('otter_login_csrf_api', $result->metadata['sources']);
        $this->assertContains('otter_check_email_api', $result->metadata['sources']);
        $this->assertSame('laravel-signed-prelogin-workspace-enrichment', $result->metadata['metadata_strategy']);
        $this->assertSame('found', $result->metadata['status_detail']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
        $this->assertSame('disp.oxylabs.io:8008', $result->metadata['proxy_used']);
        $this->assertSame(200, $result->metadata['http_status']);
        $this->assertArrayHasKey('latency_ms', $result->metadata);
        $this->assertSame(0.96, $result->confidence);
    }

    public function test_otter_not_registered_result_exposes_account_absence_signal(): void
    {
        Http::fake([
            'https://otter.ai/forward/api/v1/login_csrf' => Http::response([
                'status' => 'OK',
                'logged-in' => false,
            ], 200, [
                'Set-Cookie' => 'csrftoken=test-csrf-token; Path=/; Secure',
            ]),
            'https://otter.ai/forward/api/v1/check_email*' => Http::response([
                'status' => 'OK',
                'user_email' => false,
                'email_verified' => false,
                'email_host' => 'google',
                'is_personal' => true,
                'domain_label' => 'personal',
                'workspace' => [],
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'otter',
            target: 'jane@example.com',
            options: [],
        );

        $this->assertSame('Not Registered', $result->status);
        $this->assertFalse((bool) $result->metadata['account_exists']);
        $this->assertFalse((bool) $result->metadata['email_verified']);
        $this->assertSame('google', $result->metadata['email_host']);
        $this->assertSame('personal', $result->metadata['domain_label']);
        $this->assertSame('not_found', $result->metadata['status_detail']);
        $this->assertSame(2, $result->metadata['observed_metadata_level']);
        $this->assertSame(0.94, $result->confidence);
    }

    public function test_otter_bootstraps_csrf_before_check_email(): void
    {
        Http::fake([
            'https://otter.ai/forward/api/v1/login_csrf' => Http::response([
                'status' => 'OK',
                'logged-in' => false,
            ], 200, [
                'Set-Cookie' => 'csrftoken=bootstrapped-token; Path=/; Secure',
            ]),
            'https://otter.ai/forward/api/v1/check_email*' => function (\Illuminate\Http\Client\Request $request) {
                $cookieHeader = $request->header('Cookie')[0] ?? '';
                $this->assertSame('bootstrapped-token', $request->header('X-CSRFToken')[0] ?? null);
                $this->assertStringContainsString('csrftoken=bootstrapped-token', $cookieHeader);
                $this->assertSame('https://otter.ai/signin', $request->header('Referer')[0] ?? null);
                $this->assertSame('https://otter.ai', $request->header('Origin')[0] ?? null);
                $this->assertStringContainsString('name="email"', $request->body());
                $this->assertStringContainsString('jane@example.com', $request->body());
                $this->assertStringContainsString('appid=otter-web', $request->url());

                return Http::response([
                    'status' => 'OK',
                    'user_email' => false,
                    'email_verified' => false,
                    'workspace' => [],
                ], 200);
            },
        ]);

        $result = (new OtterEmailValidator())->check('jane@example.com');

        $this->assertSame('Not Registered', $result->status);
    }

    public function test_otter_invalid_email_returns_error(): void
    {
        Http::fake([
            'https://otter.ai/forward/api/v1/login_csrf' => Http::response([
                'status' => 'OK',
                'logged-in' => false,
            ], 200, [
                'Set-Cookie' => 'csrftoken=test-csrf-token; Path=/; Secure',
            ]),
            'https://otter.ai/forward/api/v1/check_email*' => Http::response([
                'status' => 'failed',
                'message' => 'Invalid email',
                'code' => 4,
            ], 400),
        ]);

        $result = (new OtterEmailValidator())->check('not-an-email');

        $this->assertSame('Error', $result->status);
        $this->assertSame('Invalid email', $result->reason);
    }

    public function test_walmart_registered_result_exposes_structured_metadata(): void
    {
        Http::fake([
            'https://identity.walmart.com/account/login*' => Http::response('', 200),
            'https://identity.walmart.com/orchestra/idp/graphql' => Http::response([
                'data' => [
                    'getLoginOptions' => [
                        'loginOptions' => [
                            'signInPreference' => 'PASSWORD',
                            'loginMaskedEmailId' => 'j***@example.com',
                            'maskedPhoneNumberDetails' => [
                                'loginPhoneLastFour' => '4321',
                            ],
                            'hasPasskeyOnProfile' => true,
                            'accountDomain' => 'example.com',
                            'residencyRegion' => [
                                'residencyCountryCode' => 'US',
                            ],
                        ],
                        'errors' => [],
                    ],
                ],
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'walmart',
            target: 'jane@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('jane@example.com', $result->metadata['public_email']);
        $this->assertSame('PASSWORD', $result->metadata['sign_in_preference']);
        $this->assertSame('j***@example.com', $result->metadata['masked_email']);
        $this->assertSame('***4321', $result->metadata['masked_phone']);
        $this->assertTrue((bool) $result->metadata['has_passkey_on_profile']);
        $this->assertSame('example.com', $result->metadata['account_domain']);
        $this->assertSame('US', $result->metadata['residency_country']);
        $this->assertSame('active', $result->metadata['account_status']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_walmart_bootstraps_session_before_graphql_lookup(): void
    {
        Http::fake([
            'https://identity.walmart.com/account/login*' => Http::response('', 200, [
                'Set-Cookie' => 'session=sess-123; Path=/; Secure',
            ]),
            'https://identity.walmart.com/orchestra/idp/graphql' => function (\Illuminate\Http\Client\Request $request) {
                $cookieHeader = $request->header('Cookie')[0] ?? '';
                $this->assertStringContainsString('session=sess-123', $cookieHeader);

                return Http::response([
                    'data' => [
                        'getLoginOptions' => [
                            'loginOptions' => [
                                'signInPreference' => 'CREATE',
                            ],
                            'errors' => [],
                        ],
                    ],
                ], 200);
            },
        ]);

        $result = (new WalmartValidator())->check('jane@example.com');

        $this->assertSame('Not Registered', $result->status);
    }

    public function test_samsclub_registered_result_includes_account_metadata(): void
    {
        Http::fake([
            'https://identity.samsclub.com/account/login*' => Http::response('', 200),
            'https://identity.samsclub.com/orchestra/idp/graphql' => Http::response([
                'data' => [
                    'getLoginOptions' => [
                        'loginOptions' => [
                            'signInPreference' => 'PASSWORD',
                            'loginPreference' => 'PASSWORD',
                            'lastLoginPreference' => 'PASSWORD',
                            'loginMaskedEmailId' => 'j***@example.com',
                            'maskedPhoneNumberDetails' => [
                                'loginPhoneLastFour' => '4321',
                            ],
                            'canUsePassword' => true,
                            'canUsePhoneOTP' => false,
                            'canUseEmailOTP' => true,
                            'hasPasskeyOnProfile' => true,
                            'isPhoneConnected' => true,
                            'phoneCollectionRequired' => false,
                        ],
                        'errors' => [],
                    ],
                ],
            ], 200),
        ]);

        $result = (new SamsclubEmailValidator())->check('jane@example.com');

        $this->assertSame('Registered', $result->status);
        $this->assertSame(
            "Sign-in preference: PASSWORD\nLogin preference: PASSWORD\nLast login preference: PASSWORD\nMasked email: j***@example.com\nMasked phone: ***4321\nPassword sign-in: Yes\nPhone OTP: No\nEmail OTP: Yes\nPasskey on profile: Yes\nPhone connected: Yes\nPhone collection required: No",
            $result->extra
        );
    }

    public function test_samsclub_registered_result_exposes_structured_metadata(): void
    {
        Http::fake([
            'https://identity.samsclub.com/account/login*' => Http::response('', 200),
            'https://identity.samsclub.com/orchestra/idp/graphql' => Http::response([
                'data' => [
                    'getLoginOptions' => [
                        'loginOptions' => [
                            'signInPreference' => 'CHOICE',
                            'loginPreference' => 'PASSWORD',
                            'lastLoginPreference' => 'EMAIL_OTP',
                            'loginMaskedEmailId' => 'j***@example.com',
                            'maskedPhoneNumberDetails' => [
                                'loginPhoneLastFour' => '4321',
                            ],
                            'canUsePassword' => true,
                            'canUsePhoneOTP' => false,
                            'canUseEmailOTP' => true,
                            'hasPasskeyOnProfile' => true,
                            'isPhoneConnected' => true,
                            'phoneCollectionRequired' => false,
                        ],
                        'errors' => [],
                    ],
                ],
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'samsclub',
            target: 'jane@example.com',
            options: ['proxy' => 'http://user:pass@disp.oxylabs.io:8008'],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('jane@example.com', $result->metadata['public_email']);
        $this->assertTrue((bool) $result->metadata['account_exists']);
        $this->assertSame('CHOICE', $result->metadata['sign_in_preference']);
        $this->assertSame('PASSWORD', $result->metadata['login_preference']);
        $this->assertSame('EMAIL_OTP', $result->metadata['last_login_preference']);
        $this->assertSame('j***@example.com', $result->metadata['masked_email']);
        $this->assertSame('4321', $result->metadata['phone_last_four']);
        $this->assertSame('***4321', $result->metadata['masked_phone']);
        $this->assertTrue((bool) $result->metadata['can_use_password']);
        $this->assertFalse((bool) $result->metadata['can_use_phone_otp']);
        $this->assertTrue((bool) $result->metadata['can_use_email_otp']);
        $this->assertTrue((bool) $result->metadata['has_passkey']);
        $this->assertTrue((bool) $result->metadata['is_phone_connected']);
        $this->assertFalse((bool) $result->metadata['phone_collection_required']);
        $this->assertSame('active', $result->metadata['account_status']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertContains('samsclub_login_options_api', $result->metadata['sources']);
        $this->assertSame(['phone_last_four', 'masked_phone', 'masked_email', 'can_use_password', 'can_use_phone_otp', 'can_use_email_otp', 'has_passkey', 'sign_in_preference', 'login_preference', 'last_login_preference', 'is_phone_connected', 'phone_collection_required', 'account_status'], $result->metadata['sensitive_fields']);
        $this->assertSame('disp.oxylabs.io:8008', $result->metadata['proxy_used']);
        $this->assertSame(200, $result->metadata['http_status']);
        $this->assertArrayHasKey('latency_ms', $result->metadata);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
        $this->assertSame(0.96, $result->confidence);
    }

    public function test_samsclub_compromised_result_exposes_account_status(): void
    {
        Http::fake([
            'https://identity.samsclub.com/account/login*' => Http::response('', 200),
            'https://identity.samsclub.com/orchestra/idp/graphql' => Http::response([
                'data' => [
                    'getLoginOptions' => [
                        'loginOptions' => [
                            'signInPreference' => 'PASSWORD',
                            'canUsePassword' => true,
                        ],
                        'errors' => [
                            ['code' => 'COMPROMISED'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'samsclub',
            target: 'jane@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('Account flagged as compromised', $result->reason);
        $this->assertSame('compromised', $result->metadata['account_status']);
        $this->assertSame(['COMPROMISED'], $result->metadata['lookup_errors']);
    }

    public function test_samsclub_bootstraps_session_before_graphql_lookup(): void
    {
        Http::fake([
            'https://identity.samsclub.com/account/login*' => Http::response('', 200, [
                'Set-Cookie' => 'session=sess-456; Path=/; Secure',
            ]),
            'https://identity.samsclub.com/orchestra/idp/graphql' => function (\Illuminate\Http\Client\Request $request) {
                $cookieHeader = $request->header('Cookie')[0] ?? '';
                $this->assertStringContainsString('session=sess-456', $cookieHeader);

                return Http::response([
                    'data' => [
                        'getLoginOptions' => [
                            'loginOptions' => [
                                'signInPreference' => 'CREATE',
                                'loginPreference' => 'CREATE_BLOCK',
                            ],
                            'errors' => [],
                        ],
                    ],
                ], 200);
            },
        ]);

        $result = (new SamsclubEmailValidator())->check('jane@example.com');

        $this->assertSame('Not Registered', $result->status);
    }

    public function test_samsclub_not_registered_result_exposes_account_absence_signal(): void
    {
        Http::fake([
            'https://identity.samsclub.com/account/login*' => Http::response('', 200),
            'https://identity.samsclub.com/orchestra/idp/graphql' => Http::response([
                'data' => [
                    'getLoginOptions' => [
                        'loginOptions' => [
                            'signInPreference' => 'CREATE',
                            'loginPreference' => 'CREATE_BLOCK',
                            'canUsePassword' => false,
                            'canUsePhoneOTP' => false,
                            'canUseEmailOTP' => false,
                            'hasPasskeyOnProfile' => false,
                            'isPhoneConnected' => false,
                            'phoneCollectionRequired' => false,
                        ],
                        'errors' => [],
                    ],
                ],
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'samsclub',
            target: 'jane@example.com',
            options: [],
        );

        $this->assertSame('Not Registered', $result->status);
        $this->assertFalse((bool) $result->metadata['account_exists']);
        $this->assertSame('CREATE', $result->metadata['sign_in_preference']);
        $this->assertSame('CREATE_BLOCK', $result->metadata['login_preference']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertContains('samsclub_login_options_api', $result->metadata['sources']);
        $this->assertSame(0.95, $result->confidence);
    }

    public function test_etsy_registered_result_exposes_structured_metadata(): void
    {
        Http::fake([
            'https://www.etsy.com/api/v3/ajax/public/users/by-identity-optional*' => Http::response([
                'user_id' => 12345,
                'real_name' => 'Kaif Codec',
                'login_name' => 'kaifcodec',
                'gender' => 'male',
                'location' => 'Lagos, Nigeria',
                'bio' => 'Selling handmade code.',
                'is_seller' => true,
                'has_page' => true,
                'favorite_items_public' => true,
                'favorite_shops_public' => false,
                'follower_count' => 42,
                'following_count' => 10,
                'num_favorites' => 300,
                'avatar' => ['url' => 'https://img.etsystatic.com/avatar.jpg'],
                'create_date' => 1700000000,
                'update_date' => 1710000000,
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'etsy',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame(12345, $result->metadata['etsy_user_id']);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('kaifcodec@example.com', $result->metadata['public_email']);
        $this->assertSame('male', $result->metadata['gender']);
        $this->assertSame('Lagos, Nigeria', $result->metadata['location']);
        $this->assertSame('Selling handmade code.', $result->metadata['bio']);
        $this->assertTrue((bool) $result->metadata['is_seller']);
        $this->assertTrue((bool) $result->metadata['has_public_page']);
        $this->assertTrue((bool) $result->metadata['favorite_items_public']);
        $this->assertFalse((bool) $result->metadata['favorite_shops_public']);
        $this->assertSame(42, $result->metadata['followers']);
        $this->assertSame(10, $result->metadata['following']);
        $this->assertSame(300, $result->metadata['favorites_count']);
        $this->assertSame('https://img.etsystatic.com/avatar.jpg', $result->metadata['avatar_url']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_allen_registered_result_exposes_structured_metadata(): void
    {
        Http::fake([
            'https://api.allen-live.in/api/v1/user/identities/*' => Http::response([
                'status' => 200,
                'reason' => 'OK',
                'data' => [
                    'identities' => [
                        ['identity_type' => 'EMAIL', 'identity_value' => 'kaifcodec@example.com'],
                        ['identity_type' => 'PHONE', 'identity_value' => '9876543210'],
                    ],
                ],
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'allen',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('kaifcodec@example.com', $result->metadata['public_email']);
        $this->assertTrue((bool) $result->metadata['account_exists']);
        $this->assertSame('+919876543210', $result->metadata['phone']);
        $this->assertSame(['phone'], $result->metadata['sensitive_fields']);
        $this->assertContains('identity_api', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
        $this->assertSame(200, $result->metadata['http_status']);
        $this->assertArrayHasKey('latency_ms', $result->metadata);
        $this->assertSame(0.9, $result->confidence);
    }

    public function test_vedantu_registered_result_exposes_structured_metadata(): void
    {
        Http::fake([
            'https://user.vedantu.com/user/preLoginVerification' => Http::response([
                'emailExists' => true,
                'phone' => '+91******4321',
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'vedantu',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('kaifcodec@example.com', $result->metadata['public_email']);
        $this->assertTrue((bool) $result->metadata['account_exists']);
        $this->assertSame('+91******4321', $result->metadata['phone']);
        $this->assertSame(['phone'], $result->metadata['sensitive_fields']);
        $this->assertContains('pre_login_verification_api', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
        $this->assertSame(200, $result->metadata['http_status']);
        $this->assertArrayHasKey('latency_ms', $result->metadata);
        $this->assertSame(0.9, $result->confidence);
    }

    public function test_indiatimes_unverified_registered_result_exposes_structured_metadata(): void
    {
        Http::fake([
            'https://jsso.indiatimes.com/sso/crossapp/identity/web/checkUserExists' => Http::response([
                'data' => ['status' => 'UNVERIFIED_EMAIL'],
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'indiatimes',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('kaifcodec@example.com', $result->metadata['public_email']);
        $this->assertTrue((bool) $result->metadata['account_exists']);
        $this->assertSame('unverified', $result->metadata['email_verification_status']);
        $this->assertFalse((bool) $result->metadata['is_verified']);
        $this->assertContains('identity_api', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
        $this->assertSame(200, $result->metadata['http_status']);
        $this->assertArrayHasKey('latency_ms', $result->metadata);
        $this->assertSame(0.84, $result->confidence);
    }

    public function test_vivino_locked_registered_result_exposes_structured_metadata(): void
    {
        Http::fake([
            'https://www.vivino.com/' => Http::response('', 200),
            'https://www.vivino.com/api/login' => Http::response([
                'error' => 'Account has been locked',
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'vivino',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('kaifcodec@example.com', $result->metadata['public_email']);
        $this->assertTrue((bool) $result->metadata['account_exists']);
        $this->assertSame('locked', $result->metadata['account_status']);
        $this->assertContains('login_api', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
        $this->assertSame(200, $result->metadata['http_status']);
        $this->assertArrayHasKey('latency_ms', $result->metadata);
        $this->assertSame(0.86, $result->confidence);
    }

    public function test_eventbrite_registered_result_exposes_lookup_metadata_and_diagnostics(): void
    {
        Http::fake([
            'https://www.eventbrite.com/signin/' => Http::response('', 200, [
                'Set-Cookie' => 'csrftoken=test-csrf-token; Path=/; Secure',
            ]),
            'https://www.eventbrite.com/api/v3/users/lookup/' => Http::response([
                'exists' => true,
                'user_id' => 'evt_123',
                'can_login' => true,
                'is_email_verified' => true,
                'sign_in_methods' => ['password', 'google'],
                'is_organizer' => false,
                'mfa_enabled' => true,
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'eventbrite',
            target: 'kaifcodec@example.com',
            options: ['proxy' => 'http://user:pass@disp.oxylabs.io:8008'],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('evt_123', $result->metadata['user_id']);
        $this->assertTrue((bool) $result->metadata['can_login']);
        $this->assertTrue((bool) $result->metadata['is_email_verified']);
        $this->assertSame(['password', 'google'], $result->metadata['sign_in_methods']);
        $this->assertFalse((bool) $result->metadata['is_organizer']);
        $this->assertTrue((bool) $result->metadata['mfa_enabled']);
        $this->assertSame(['sign_in_methods', 'mfa_enabled'], $result->metadata['sensitive_fields']);
        $this->assertSame('disp.oxylabs.io:8008', $result->metadata['proxy_used']);
        $this->assertSame(200, $result->metadata['http_status']);
        $this->assertArrayHasKey('latency_ms', $result->metadata);
        $this->assertContains('eventbrite_lookup_api', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
    }

    public function test_appletv_registered_result_exposes_auth_metadata(): void
    {
        Http::fake([
            'https://idmsa.apple.com/appleauth/auth/federate*' => Http::response([
                'displayName' => 'Kaif Codec',
                'primaryAuthOptions' => [
                    ['type' => 'password'],
                    ['type' => 'apple_id'],
                ],
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'appletv',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame(['password', 'apple_id'], $result->metadata['sign_in_methods']);
        $this->assertSame(['sign_in_methods'], $result->metadata['sensitive_fields']);
        $this->assertTrue((bool) $result->metadata['account_exists']);
        $this->assertContains('apple_federate', $result->metadata['sources']);
    }

    public function test_duolingo_registered_result_exposes_profile_metadata_and_profile_url(): void
    {
        Http::fake([
            'https://www.duolingo.com/2017-06-30/users*' => Http::response([
                'users' => [[
                    'id' => 4242,
                    'username' => 'kaifcodec',
                    'name' => 'Kaif Codec',
                    'picture' => 'https://d35aaqx5ub95lt.cloudfront.net/avatar.png',
                    'hasGoogleId' => true,
                    'hasFacebookId' => false,
                    'hasPlus' => true,
                    'lastAccessed' => '2025-01-02T03:04:05Z',
                ]],
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'duolingo',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('https://www.duolingo.com/profile/kaifcodec', $result->profileUrl);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame(4242, $result->metadata['user_id']);
        $this->assertSame('https://d35aaqx5ub95lt.cloudfront.net/avatar.png', $result->metadata['avatar_url']);
        $this->assertTrue((bool) $result->metadata['has_google_id']);
        $this->assertFalse((bool) $result->metadata['has_facebook_id']);
        $this->assertTrue((bool) $result->metadata['has_plus']);
        $this->assertTrue((bool) $result->metadata['has_recent_activity']);
        $this->assertSame(0.96, $result->confidence);
    }

    public function test_github_registered_result_can_enrich_from_public_gravatar_evidence(): void
    {
        Http::fake([
            'https://github.com/signup' => Http::response('<input data-csrf="true" value="csrf-token">', 200),
            'https://github.com/email_validity_checks' => Http::response('This email is already associated with an account', 200),
            'https://en.gravatar.com/*.json' => Http::response([
                'entry' => [[
                    'preferredUsername' => 'kaifcodec',
                    'accounts' => [
                        ['url' => 'https://github.com/kaifcodec'],
                    ],
                ]],
            ], 200),
            'https://api.github.com/users/*' => Http::response([
                'id' => 9876,
                'login' => 'kaifcodec',
                'name' => 'Kaif Codec',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/9876?v=4',
                'bio' => 'Building scanner metadata.',
                'location' => 'Lagos, Nigeria',
                'blog' => 'https://kaif.dev',
                'followers' => 12,
                'following' => 7,
                'public_repos' => 5,
                'created_at' => '2020-01-02T03:04:05Z',
                'updated_at' => '2025-01-02T03:04:05Z',
                'company' => 'WebVetted',
                'html_url' => 'https://github.com/kaifcodec',
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'github',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('https://github.com/kaifcodec', $result->profileUrl);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame(9876, $result->metadata['user_id']);
        $this->assertSame('WebVetted', $result->metadata['company']);
        $this->assertContains('github_signup_email_validity_check', $result->metadata['sources']);
        $this->assertContains('github_public_api', $result->metadata['sources']);
        $this->assertSame(0.97, $result->confidence);
    }

    public function test_gravatar_registered_result_exposes_hashes_and_public_profile_url(): void
    {
        Http::fake([
            'https://en.gravatar.com/*.json' => Http::response([
                'entry' => [[
                    'preferredUsername' => 'kaifcodec',
                    'thumbnailUrl' => 'https://secure.gravatar.com/avatar/abc123hash',
                    'displayName' => 'Kaif Codec',
                    'aboutMe' => 'Public profile',
                    'currentLocation' => 'Lagos, Nigeria',
                    'emails' => [
                        ['value' => 'public@example.com'],
                    ],
                    'urls' => [
                        ['value' => 'https://kaif.dev'],
                    ],
                ]],
            ], 200),
        ]);

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: 'gravatar',
            target: 'kaifcodec@example.com',
            options: [],
        );

        $this->assertSame('Registered', $result->status);
        $this->assertSame('https://gravatar.com/kaifcodec', $result->profileUrl);
        $this->assertSame('kaifcodec', $result->metadata['username']);
        $this->assertSame('Kaif Codec', $result->metadata['display_name']);
        $this->assertSame('https://secure.gravatar.com/avatar/abc123hash', $result->metadata['avatar_url']);
        $this->assertSame('public@example.com', $result->metadata['public_email']);
        $this->assertSame('gravatar', $result->metadata['source']);
        $this->assertArrayHasKey('hash_md5', $result->metadata);
        $this->assertArrayHasKey('hash_sha256', $result->metadata);
        $this->assertSame(0.98, $result->confidence);
    }
}
