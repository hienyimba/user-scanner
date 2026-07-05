<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Scanner\Validators\Generated\Email\AdobeValidator;
use App\Services\Scanner\Validators\Generated\Email\CourseraValidator;
use App\Services\Scanner\Validators\Generated\Email\EtsyValidator;
use App\Services\Scanner\Validators\Generated\Email\WalmartValidator;
use App\Services\Scanner\Validators\Generated\Email\WixValidator;
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
        $this->assertSame(['password', 'google'], $result->metadata['login_methods']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(3, $result->metadata['observed_metadata_level']);
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

    public function test_walmart_registered_result_exposes_structured_metadata(): void
    {
        Http::fake([
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
        $this->assertSame('+919876543210', $result->metadata['phone']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
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
        $this->assertSame('+91******4321', $result->metadata['phone']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(4, $result->metadata['observed_metadata_level']);
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
        $this->assertSame('unverified', $result->metadata['email_verification_status']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(3, $result->metadata['observed_metadata_level']);
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
        $this->assertSame('locked', $result->metadata['account_status']);
        $this->assertContains('api_json', $result->metadata['sources']);
        $this->assertSame(3, $result->metadata['observed_metadata_level']);
    }
}
