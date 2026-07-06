<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class VedantuValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'vedantu';
    }

    public function category(): string
    {
        return 'learning';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Vedantu';
    }

    public function siteUrl(): string
    {
        return 'https://www.vedantu.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $startedAt = microtime(true);

        try {
            $request = Http::timeout(10)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Encoding' => 'identity',
                'Content-Type' => 'application/json;charset=UTF-8',
                'sec-ch-ua-platform' => '"Linux"',
                'Origin' => 'https://www.vedantu.com',
                'Referer' => 'https://www.vedantu.com/',
                'Priority' => 'u=1, i',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->withBody(json_encode([
                'email' => $target,
                'phoneCode' => null,
                'phoneNumber' => null,
                'version' => 2,
                'ver' => 1.033,
                'token' => '',
                'sType' => '',
                'sValue' => '',
                'event' => 'NEW_FLOW',
            ], JSON_THROW_ON_ERROR), 'application/json;charset=UTF-8')->post('https://user.vedantu.com/user/preLoginVerification');

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
                    'HTTP Error: ' . $response->status(),
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
                );
            }

            $data = $response->json();
            if (($data['emailExists'] ?? null) === true) {
                $maskedPhone = $data['phone'] ?? null;
                $extra = $maskedPhone ? $this->metadataSummary(['Masked phone' => $maskedPhone]) : '';
                $metadata = [
                    'public_email' => $target,
                    'account_exists' => true,
                    'sources' => ['api_json', 'pre_login_verification_api'],
                ];
                if (is_string($maskedPhone) && $maskedPhone !== '') {
                    $metadata['phone'] = $maskedPhone;
                    $metadata['sensitive_fields'] = ['phone'];
                }

                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Registered',
                    '',
                    $extra,
                    mode: $this->mode(),
                    key: $this->key(),
                    confidence: $maskedPhone ? 0.9 : 0.84,
                    metadata: $this->mergeRequestDiagnostics($metadata, $options, $response, $startedAt),
                );
            }
            if (($data['emailExists'] ?? null) === false) {
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

            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                'Error',
                'Unexpected response body structure',
                mode: $this->mode(),
                key: $this->key(),
                metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
            );
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = str_contains($message, 'timed out')
                ? (str_contains($message, 'read') ? 'Server took too long to respond (Read Timeout)' : 'Connection timed out! maybe region blocks')
                : $e->getMessage();

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

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
