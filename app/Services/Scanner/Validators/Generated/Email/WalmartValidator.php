<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class WalmartValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'walmart';
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
        return 'Walmart';
    }

    public function siteUrl(): string
    {
        return 'https://walmart.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $uid = str_replace('-', '', (string) Str::uuid());
        $traceId = '00-' . substr($uid, 0, 32) . '-' . substr($uid, 0, 16) . '-00';
        $corrId = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        $challenge = $this->generatePkceChallenge();

        $payload = [
            'query' => 'query GetLoginOptions($input:UserOptionsInput!){getLoginOptions(input:$input){loginOptions{...LoginOptionsFragment}canUseEmailOTP phoneCollectionRequired authCode errors{...LoginOptionsErrorFragment}}}fragment LoginOptionsFragment on LoginOptions{loginId loginIdType emailId phoneNumber{number countryCode isoCountryCode}canUsePassword canUsePhoneOTP canUseEmailOTP loginPhoneLastFour maskedPhoneNumberDetails{loginPhoneLastFour countryCode isoCountryCode}loginMaskedEmailId signInPreference loginPreference lastLoginPreference hasRemainingFactors isPhoneConnected otherAccountsWithPhone loginMaskedEmailId hasPasskeyOnProfile accountDomain residencyRegion{residencyCountryCode residencyRegionCode}isIdentityMergeRequired}fragment LoginOptionsErrorFragment on IdentityLoginOptionsError{code message version}',
            'variables' => [
                'input' => [
                    'loginId' => $target,
                    'loginIdType' => 'EMAIL',
                    'ssoOptions' => [
                        'wasConsentCaptured' => true,
                        'callbackUrl' => 'https://www.walmart.com/account/verifyToken',
                        'clientId' => '5f3fb121-076a-45f6-9587-249f0bc160ff',
                        'scope' => 'openid email offline_access',
                        'state' => '/account/delete-account',
                        'challenge' => $challenge,
                    ],
                ],
            ],
        ];

        $loginPage = 'https://identity.walmart.com/account/login?scope=openid%20email%20offline_access&redirect_uri=https%3A%2F%2Fwww.walmart.com%2Faccount%2FverifyToken&client_id=5f3fb121-076a-45f6-9587-249f0bc160ff&tenant_id=elh9ie&code_challenge=' . $challenge . '&state=%2Faccount%2Fdelete-account';

        try {
            $request = Http::timeout(10)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
                'Accept' => 'application/json',
                'Accept-Encoding' => 'identity',
                'Content-Type' => 'application/json',
                'x-o-mart' => 'B2C',
                'x-o-gql-query' => 'query GetLoginOptions',
                'sec-ch-ua-platform' => '"Android"',
                'x-o-segment' => 'oaoh',
                'device_profile_ref_id' => 'xpmjgxfheteohb199lo0r5qewtwjywqaifje',
                'sec-ch-ua' => '"Not:A-Brand";v="99", "Google Chrome";v="145", "Chromium";v="145"',
                'x-enable-server-timing' => '1',
                'sec-ch-ua-mobile' => '?1',
                'x-latency-trace' => '1',
                'traceparent' => $traceId,
                'wm_mp' => 'true',
                'x-apollo-operation-name' => 'GetLoginOptions',
                'tenant-id' => 'elh9ie',
                'downlink' => '10',
                'wm_qos.correlation_id' => $corrId,
                'x-o-platform' => 'rweb',
                'x-o-platform-version' => 'usweb-1.244.0-11a85c27f6b1cd480b5bbfc2090ace49df92f6fc-2190302r',
                'accept-language' => 'en-US',
                'x-o-ccm' => 'server',
                'x-o-bu' => 'WALMART-US',
                'dpr' => '2.75',
                'wm_page_url' => $loginPage,
                'x-o-correlation-id' => $corrId,
                'origin' => 'https://identity.walmart.com',
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-dest' => 'empty',
                'referer' => $loginPage,
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->withBody(json_encode($payload, JSON_THROW_ON_ERROR), 'application/json')
                ->post('https://identity.walmart.com/orchestra/idp/graphql');

            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() === 412) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Precondition Failed (412) - Walmart detected session mismatch', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'HTTP Error: ' . $response->status(), mode: $this->mode(), key: $this->key());
            }

            $loginOptions = $response->json('data.getLoginOptions.loginOptions') ?? [];
            $errors = $response->json('data.getLoginOptions.errors') ?? [];
            $pref = (string) ($loginOptions['signInPreference'] ?? '');
            $maskedPhoneLastFour = (string) ($loginOptions['loginPhoneLastFour'] ?? data_get($loginOptions, 'maskedPhoneNumberDetails.loginPhoneLastFour', ''));
            $maskedPhone = $maskedPhoneLastFour !== '' ? '***' . $maskedPhoneLastFour : null;
            $extra = $this->metadataSummary([
                'Sign-in preference' => $pref !== '' ? $pref : null,
                'Masked email' => $loginOptions['loginMaskedEmailId'] ?? null,
                'Masked phone' => $maskedPhone,
                'Passkey on profile' => $loginOptions['hasPasskeyOnProfile'] ?? null,
                'Account domain' => $loginOptions['accountDomain'] ?? null,
                'Residency country' => data_get($loginOptions, 'residencyRegion.residencyCountryCode'),
            ]);
            $metadata = [
                'public_email' => $target,
                'sources' => ['api_json'],
            ];
            if ($pref !== '') {
                $metadata['sign_in_preference'] = $pref;
            }
            if (is_string($loginOptions['loginMaskedEmailId'] ?? null) && trim((string) $loginOptions['loginMaskedEmailId']) !== '') {
                $metadata['masked_email'] = trim((string) $loginOptions['loginMaskedEmailId']);
            }
            if ($maskedPhone !== null) {
                $metadata['masked_phone'] = $maskedPhone;
            }
            if (array_key_exists('hasPasskeyOnProfile', $loginOptions) && is_bool($loginOptions['hasPasskeyOnProfile'])) {
                $metadata['has_passkey_on_profile'] = (bool) $loginOptions['hasPasskeyOnProfile'];
            }
            if (is_string($loginOptions['accountDomain'] ?? null) && trim((string) $loginOptions['accountDomain']) !== '') {
                $metadata['account_domain'] = trim((string) $loginOptions['accountDomain']);
            }
            $residencyCountry = data_get($loginOptions, 'residencyRegion.residencyCountryCode');
            if (is_scalar($residencyCountry) && trim((string) $residencyCountry) !== '') {
                $metadata['residency_country'] = trim((string) $residencyCountry);
            }

            if (in_array($pref, ['PASSWORD', 'CHOICE'], true)) {
                foreach ($errors as $error) {
                    if (($error['code'] ?? null) === 'COMPROMISED') {
                        $metadata['account_status'] = 'compromised';

                        return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', 'Account flagged as compromised', $extra, mode: $this->mode(), key: $this->key(), metadata: $metadata);
                    }
                }

                $metadata['account_status'] = 'active';

                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', $extra, mode: $this->mode(), key: $this->key(), metadata: $metadata);
            }
            if ($pref === 'CREATE') {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response structure', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    private function generatePkceChallenge(): string
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash = hash('sha256', $verifier, true);

        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }
}
