<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class SamsclubEmailValidator extends BaseGeneratedValidator
{
    private const CLIENT_ID = '257922ea-a4b8-4342-a251-e76744be5d6d';
    private const GRAPHQL_URL = 'https://identity.samsclub.com/orchestra/idp/graphql';
    private const LOGIN_URL = 'https://identity.samsclub.com/account/login';
    private const REDIRECT_URI = 'https://www.samsclub.com/account/verifyToken';
    private const SENSITIVE_FIELDS = [
        'phone_last_four',
        'masked_phone',
        'masked_email',
        'can_use_password',
        'can_use_phone_otp',
        'can_use_email_otp',
        'has_passkey',
        'sign_in_preference',
        'login_preference',
        'last_login_preference',
        'is_phone_connected',
        'phone_collection_required',
        'account_status',
    ];
    private const TENANT_ID = 'gj9b60';

    public function key(): string
    {
        return 'samsclub';
    }

    public function category(): string
    {
        return 'shopping';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'SamsClub';
    }

    public function siteUrl(): string
    {
        return 'https://www.samsclub.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $startedAt = microtime(true);
        $uid = str_replace('-', '', (string) Str::uuid());
        $traceId = '00-' . substr($uid, 0, 32) . '-' . substr($uid, 0, 16) . '-00';
        $correlationId = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        $challenge = $this->generatePkceChallenge();
        $cookieJar = new CookieJar();
        $loginPage = $this->buildLoginPageUrl($challenge);
        $payload = $this->buildLookupPayload($target, $challenge);

        try {
            $request = Http::timeout(10)->withOptions([
                'allow_redirects' => true,
                'cookies' => $cookieJar,
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => config('scanner.user_agent'),
                'Accept' => 'application/json',
                'Accept-Encoding' => 'identity',
                'Content-Type' => 'application/json',
                'traceparent' => $traceId,
                'tenant-id' => self::TENANT_ID,
                'x-apollo-operation-name' => 'GetLoginOptions',
                'x-o-gql-query' => 'query GetLoginOptions',
                'x-o-correlation-id' => $correlationId,
                'wm_qos.correlation_id' => $correlationId,
                'origin' => 'https://identity.samsclub.com',
                'referer' => $loginPage,
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-dest' => 'empty',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $bootstrap = $request->get($loginPage);
            if ($blocked = $this->detectBlockedOrChallenged($bootstrap)) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    $blocked[0],
                    $blocked[1],
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $bootstrap, $startedAt),
                );
            }

            $response = $request->withBody(json_encode($payload, JSON_THROW_ON_ERROR), 'application/json')
                ->post(self::GRAPHQL_URL);

            if ($response->status() === 429) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Error',
                    'Rate limited',
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
                );
            }

            if ($response->status() === 412) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Error',
                    'Precondition Failed (412) - SamsClub detected session mismatch',
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
                );
            }

            if ($response->status() !== 200) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Error',
                    'HTTP Error: ' . $response->status(),
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
                );
            }

            $lookup = $response->json('data.getLoginOptions');
            if (!is_array($lookup)) {
                $lookup = [];
            }
            $loginOptions = is_array($lookup['loginOptions'] ?? null) ? $lookup['loginOptions'] : [];
            $errors = is_array($lookup['errors'] ?? null) ? $lookup['errors'] : [];

            $signInPreference = $this->stringValue($loginOptions['signInPreference'] ?? null);
            if ($signInPreference === null) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Error',
                    'Unexpected response structure',
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
                );
            }

            $metadata = $this->buildMetadata($target, $loginOptions, $errors);
            $extra = $this->buildExtra($loginOptions);

            if ($signInPreference === 'CREATE') {
                $metadata['account_exists'] = false;
                $metadata['status_detail'] = 'not_found';
                $metadata['observed_metadata_level'] = 1;

                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Not Registered',
                    '',
                    $extra,
                    mode: $this->mode(),
                    key: $this->key(),
                    confidence: 0.95,
                    metadata: $this->mergeRequestDiagnostics($metadata, $options, $response, $startedAt),
                );
            }

            $metadata['account_exists'] = true;
            $metadata['status_detail'] = 'found';
            $metadata['observed_metadata_level'] = 4;
            $reason = '';
            foreach ($errors as $error) {
                if (($error['code'] ?? null) === 'COMPROMISED') {
                    $metadata['account_status'] = 'compromised';
                    $reason = 'Account flagged as compromised';
                    break;
                }
            }

            $metadata['account_status'] ??= 'active';

            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Registered',
                $reason,
                $extra,
                mode: $this->mode(),
                key: $this->key(),
                confidence: 0.96,
                metadata: $this->mergeRequestDiagnostics($metadata, $options, $response, $startedAt),
            );
        } catch (\Throwable $e) {
            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Error',
                $e->getMessage(),
                mode: $this->mode(),
                key: $this->key(),
                metadata: $this->requestDiagnostics($options, null, $startedAt),
            );
        }
    }

    /**
     * @param array<string, mixed> $loginOptions
     * @param array<int, array<string, mixed>> $errors
     * @return array<string, mixed>
     */
    private function buildMetadata(string $target, array $loginOptions, array $errors): array
    {
        $phoneLastFour = $this->stringValue(
            $loginOptions['loginPhoneLastFour'] ?? data_get($loginOptions, 'maskedPhoneNumberDetails.loginPhoneLastFour')
        );
        $maskedPhone = $phoneLastFour !== null ? '***' . $phoneLastFour : null;
        $metadata = [
            'public_email' => $target,
            'sources' => ['api_json', 'samsclub_login_options_api'],
            'sensitive_fields' => self::SENSITIVE_FIELDS,
            'metadata_strategy' => 'laravel-direct-auth-enrichment',
        ];

        foreach ([
            'signInPreference' => 'sign_in_preference',
            'loginPreference' => 'login_preference',
            'lastLoginPreference' => 'last_login_preference',
            'loginMaskedEmailId' => 'masked_email',
        ] as $sourceKey => $targetKey) {
            $value = $this->stringValue($loginOptions[$sourceKey] ?? null);
            if ($value !== null) {
                $metadata[$targetKey] = $value;
            }
        }

        if ($phoneLastFour !== null) {
            $metadata['phone_last_four'] = $phoneLastFour;
            $metadata['masked_phone'] = $maskedPhone;
        }

        foreach ([
            'canUsePassword' => 'can_use_password',
            'canUsePhoneOTP' => 'can_use_phone_otp',
            'canUseEmailOTP' => 'can_use_email_otp',
            'hasPasskeyOnProfile' => 'has_passkey',
            'isPhoneConnected' => 'is_phone_connected',
            'phoneCollectionRequired' => 'phone_collection_required',
        ] as $sourceKey => $targetKey) {
            if (is_bool($loginOptions[$sourceKey] ?? null)) {
                $metadata[$targetKey] = (bool) $loginOptions[$sourceKey];
            }
        }

        if ($errors !== []) {
            $metadata['lookup_errors'] = array_values(array_filter(array_map(
                fn (array $error): ?string => $this->stringValue($error['code'] ?? null),
                $errors
            )));
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $loginOptions
     */
    private function buildExtra(array $loginOptions): string
    {
        $phoneLastFour = $this->stringValue(
            $loginOptions['loginPhoneLastFour'] ?? data_get($loginOptions, 'maskedPhoneNumberDetails.loginPhoneLastFour')
        );

        return $this->metadataSummary([
            'Sign-in preference' => $this->stringValue($loginOptions['signInPreference'] ?? null),
            'Login preference' => $this->stringValue($loginOptions['loginPreference'] ?? null),
            'Last login preference' => $this->stringValue($loginOptions['lastLoginPreference'] ?? null),
            'Masked email' => $this->stringValue($loginOptions['loginMaskedEmailId'] ?? null),
            'Masked phone' => $phoneLastFour !== null ? '***' . $phoneLastFour : null,
            'Password sign-in' => is_bool($loginOptions['canUsePassword'] ?? null) ? (bool) $loginOptions['canUsePassword'] : null,
            'Phone OTP' => is_bool($loginOptions['canUsePhoneOTP'] ?? null) ? (bool) $loginOptions['canUsePhoneOTP'] : null,
            'Email OTP' => is_bool($loginOptions['canUseEmailOTP'] ?? null) ? (bool) $loginOptions['canUseEmailOTP'] : null,
            'Passkey on profile' => is_bool($loginOptions['hasPasskeyOnProfile'] ?? null) ? (bool) $loginOptions['hasPasskeyOnProfile'] : null,
            'Phone connected' => is_bool($loginOptions['isPhoneConnected'] ?? null) ? (bool) $loginOptions['isPhoneConnected'] : null,
            'Phone collection required' => is_bool($loginOptions['phoneCollectionRequired'] ?? null) ? (bool) $loginOptions['phoneCollectionRequired'] : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLookupPayload(string $target, string $challenge): array
    {
        return [
            'query' => 'query GetLoginOptions($input:UserOptionsInput!){getLoginOptions(input:$input){loginOptions{...LoginOptionsFragment}canUseEmailOTP phoneCollectionRequired authCode errors{...LoginOptionsErrorFragment}}}fragment LoginOptionsFragment on LoginOptions{loginId loginIdType emailId phoneNumber{number countryCode isoCountryCode}canUsePassword canUsePhoneOTP canUseEmailOTP loginPhoneLastFour maskedPhoneNumberDetails{loginPhoneLastFour countryCode isoCountryCode}loginMaskedEmailId signInPreference loginPreference lastLoginPreference hasRemainingFactors isPhoneConnected otherAccountsWithPhone loginMaskedEmailId hasPasskeyOnProfile accountDomain residencyRegion{residencyCountryCode residencyRegionCode}isIdentityMergeRequired}fragment LoginOptionsErrorFragment on IdentityLoginOptionsError{code message version}',
            'variables' => [
                'input' => [
                    'loginId' => $target,
                    'loginIdType' => 'EMAIL',
                    'ssoOptions' => [
                        'wasConsentCaptured' => true,
                        'callbackUrl' => self::REDIRECT_URI,
                        'clientId' => self::CLIENT_ID,
                        'scope' => 'openid email offline_access',
                        'state' => '/account/delete-account',
                        'challenge' => $challenge,
                    ],
                ],
            ],
        ];
    }

    private function buildLoginPageUrl(string $challenge): string
    {
        return self::LOGIN_URL . '?' . http_build_query([
            'scope' => 'openid email offline_access',
            'redirect_uri' => self::REDIRECT_URI,
            'client_id' => self::CLIENT_ID,
            'tenant_id' => self::TENANT_ID,
            'code_challenge' => $challenge,
            'state' => '/account/delete-account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function generatePkceChallenge(): string
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash = hash('sha256', $verifier, true);

        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    private function stringValue(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }
}
