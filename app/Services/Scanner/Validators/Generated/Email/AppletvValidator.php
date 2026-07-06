<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Support\Facades\Http;

final class AppletvValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'appletv';
    }

    public function category(): string
    {
        return 'entertainment';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Appletv';
    }

    public function siteUrl(): string
    {
        return 'https://tv.apple.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $startedAt = microtime(true);

        try {
            $request = Http::timeout(5)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                    'query' => ['isRememberMeEnabled' => 'false'],
                ])
                ->withHeaders([
                    'User-Agent' => (string) config('scanner.user_agent'),
                    'Accept' => 'application/json, text/javascript, */*; q=0.01',
                    'Content-Type' => 'application/json',
                    'X-Apple-Domain-Id' => '2',
                    'X-Apple-Locale' => 'en_us',
                    'X-Apple-Auth-Context' => 'tv',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Origin' => 'https://idmsa.apple.com',
                    'Referer' => 'https://idmsa.apple.com/',
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->post('https://idmsa.apple.com/appleauth/auth/federate', [
                'accountName' => $target,
                'rememberMe' => false,
            ]);

            if ($blocked = $this->detectBlockedOrChallenged($response)) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    $blocked[0],
                    $blocked[1],
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
                    'HTTP ' . $response->status(),
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
                );
            }

            $data = $response->json();
            if (!is_array($data)) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Error',
                    'Unexpected response body',
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
                );
            }

            if (!array_key_exists('primaryAuthOptions', $data)) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Not Registered',
                    '',
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
                );
            }

            $metadata = [
                'sources' => ['api_json', 'apple_federate'],
                'account_exists' => true,
            ];

            $displayName = $this->nonEmptyStringValue($data['displayName'] ?? data_get($data, 'customerData.displayName'));
            if ($displayName !== null) {
                $metadata['display_name'] = $displayName;
            }

            $authOptions = [];
            foreach ((array) ($data['primaryAuthOptions'] ?? []) as $option) {
                if (!is_array($option)) {
                    continue;
                }

                $label = $this->nonEmptyStringValue($option['kind'] ?? ($option['type'] ?? ($option['label'] ?? ($option['id'] ?? null))));
                if ($label !== null) {
                    $authOptions[] = strtolower($label);
                }
            }
            if ($authOptions !== []) {
                $metadata['sign_in_methods'] = array_values(array_unique($authOptions));
                $metadata['sensitive_fields'] = ['sign_in_methods'];
            }

            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Registered',
                '',
                mode: $this->mode(),
                key: $this->key(),
                confidence: $displayName !== null ? 0.9 : 0.85,
                metadata: $this->mergeRequestDiagnostics($metadata, $options, $response, $startedAt),
            );
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = str_contains($message, 'timed out') ? 'Connection timed out' : $e->getMessage();

            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Error',
                $reason,
                mode: $this->mode(),
                key: $this->key(),
                metadata: $this->requestDiagnostics($options, null, $startedAt),
            );
        }
    }
}
