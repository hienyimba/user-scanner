<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class OtterEmailValidator extends BaseGeneratedValidator
{
    private const API_BASE_URL = 'https://otter.ai/forward/api/v1';
    private const APP_ID = 'otter-web';
    private const CHECK_EMAIL_URL = self::API_BASE_URL . '/check_email';
    private const HMAC_ALGORITHM = 'AS1-HMAC-SHA256';
    private const HMAC_SECRET = '0UFEMr38Hpq8msAR';
    private const LOGIN_CSRF_URL = self::API_BASE_URL . '/login_csrf';
    private const SENSITIVE_FIELDS = [
        'email_verified',
        'email_host',
        'sso_enabled',
        'sso_required',
    ];

    public function key(): string
    {
        return 'otter';
    }

    public function category(): string
    {
        return 'other';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Otter';
    }

    public function siteUrl(): string
    {
        return 'https://otter.ai';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $startedAt = microtime(true);
        $cookieJar = new CookieJar();

        try {
            $request = Http::timeout(10)
                ->withOptions([
                    'allow_redirects' => true,
                    'cookies' => $cookieJar,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->withHeaders([
                    'User-Agent' => (string) config('scanner.user_agent'),
                    'Accept' => 'application/json, text/plain, */*',
                    'Origin' => 'https://otter.ai',
                    'Referer' => 'https://otter.ai/signin',
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $bootstrap = $request->get(self::LOGIN_CSRF_URL);
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
            if ($bootstrap->status() !== 200) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Error',
                    'Unable to bootstrap CSRF token',
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $bootstrap, $startedAt),
                );
            }

            $csrfToken = $cookieJar->getCookieByName('csrftoken')?->getValue()
                ?? $this->extractCookieValue($bootstrap->header('Set-Cookie'), 'csrftoken');

            if ($csrfToken === null) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Error',
                    'CSRF token not found',
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $bootstrap, $startedAt),
                );
            }

            $signature = $this->createEmailCheckSignature($target);
            $response = $request
                ->withHeaders([
                    'X-CSRFToken' => $csrfToken,
                ])
                ->asMultipart()
                ->post(
                    self::CHECK_EMAIL_URL . '?' . http_build_query([
                        'appid' => self::APP_ID,
                        'email' => $target,
                    ], '', '&', PHP_QUERY_RFC3986),
                    [
                        ['name' => 'email', 'contents' => $target],
                        ['name' => 'algorithm', 'contents' => $signature['algorithm']],
                        ['name' => 'ts', 'contents' => $signature['ts']],
                        ['name' => 'signature', 'contents' => $signature['signature']],
                    ],
                );

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

            $data = $response->json();
            if ($response->status() === 400 && is_array($data) && ((int) ($data['code'] ?? 0) === 4)) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Error',
                    $this->stringValue($data['message'] ?? null) ?? 'Invalid email',
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
                );
            }

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

            if ($response->status() !== 200 || !is_array($data) || !is_bool($data['user_email'] ?? null)) {
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

            $metadata = $this->buildMetadata($target, $data);
            $extra = $this->buildExtra($data);

            if (($data['user_email'] ?? false) === false) {
                $metadata['account_exists'] = false;
                $metadata['status_detail'] = 'not_found';
                $metadata['observed_metadata_level'] = 2;

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
                    confidence: 0.94,
                    metadata: $this->mergeRequestDiagnostics($metadata, $options, $response, $startedAt),
                );
            }

            $metadata['account_exists'] = true;
            $metadata['status_detail'] = 'found';
            $metadata['observed_metadata_level'] = 4;

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
                confidence: 0.96,
                metadata: $this->mergeRequestDiagnostics($metadata, $options, $response, $startedAt),
            );
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = str_contains($message, 'timed out')
                ? (str_contains($message, 'read') ? 'Server took too long to respond (Read Timeout)' : 'Connection timed out')
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

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildMetadata(string $target, array $data): array
    {
        $workspace = is_array($data['workspace'] ?? null) ? $data['workspace'] : [];
        $metadata = [
            'public_email' => $target,
            'sources' => ['api_json', 'otter_login_csrf_api', 'otter_check_email_api'],
            'sensitive_fields' => self::SENSITIVE_FIELDS,
            'metadata_strategy' => 'laravel-signed-prelogin-workspace-enrichment',
        ];

        foreach ([
            'email_host' => 'email_host',
            'domain_label' => 'domain_label',
        ] as $sourceKey => $targetKey) {
            $value = $this->stringValue($data[$sourceKey] ?? null);
            if ($value !== null) {
                $metadata[$targetKey] = $value;
            }
        }

        foreach ([
            'email_verified' => 'email_verified',
            'is_personal' => 'is_personal',
        ] as $sourceKey => $targetKey) {
            if (is_bool($data[$sourceKey] ?? null)) {
                $metadata[$targetKey] = (bool) $data[$sourceKey];
            }
        }

        $workspaceId = $this->normalizeIdentifier($workspace['id'] ?? null);
        if ($workspaceId !== null) {
            $metadata['workspace_id'] = $workspaceId;
        }

        foreach ([
            'name' => 'workspace_name',
            'handle' => 'workspace_handle',
        ] as $sourceKey => $targetKey) {
            $value = $this->stringValue($workspace[$sourceKey] ?? null);
            if ($value !== null) {
                $metadata[$targetKey] = $value;
            }
        }

        foreach ([
            'is_pending_member' => 'is_pending_member',
            'sso_required' => 'sso_required',
            'disable_sso_sandbox' => 'disable_sso_sandbox',
        ] as $sourceKey => $targetKey) {
            if (is_bool($workspace[$sourceKey] ?? null)) {
                $metadata[$targetKey] = (bool) $workspace[$sourceKey];
            }
        }

        if (array_key_exists('sso_enabled', $workspace) && is_bool($workspace['sso_enabled'])) {
            $metadata['sso_enabled'] = (bool) $workspace['sso_enabled'];
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildExtra(array $data): string
    {
        $workspace = is_array($data['workspace'] ?? null) ? $data['workspace'] : [];

        return $this->metadataSummary([
            'Email verified' => is_bool($data['email_verified'] ?? null) ? (bool) $data['email_verified'] : null,
            'Email host' => $this->stringValue($data['email_host'] ?? null),
            'Personal domain' => is_bool($data['is_personal'] ?? null) ? (bool) $data['is_personal'] : null,
            'Domain label' => $this->stringValue($data['domain_label'] ?? null),
            'Workspace ID' => $this->normalizeIdentifier($workspace['id'] ?? null),
            'Workspace name' => $this->stringValue($workspace['name'] ?? null),
            'Workspace handle' => $this->stringValue($workspace['handle'] ?? null),
            'Pending member' => is_bool($workspace['is_pending_member'] ?? null) ? (bool) $workspace['is_pending_member'] : null,
            'SSO required' => is_bool($workspace['sso_required'] ?? null) ? (bool) $workspace['sso_required'] : null,
        ]);
    }

    /**
     * @return array{algorithm:string,signature:string,ts:string}
     */
    private function createEmailCheckSignature(string $email): array
    {
        $timestamp = (string) time();
        $payload = sprintf(
            'algorithm=%s&email=%s&ts=%s',
            self::HMAC_ALGORITHM,
            $email,
            $timestamp,
        );

        return [
            'algorithm' => self::HMAC_ALGORITHM,
            'signature' => hash_hmac('sha256', $payload, self::HMAC_SECRET),
            'ts' => $timestamp,
        ];
    }

    private function extractCookieValue(array|string|null $cookieHeader, string $cookieName): ?string
    {
        $headers = is_array($cookieHeader) ? $cookieHeader : [$cookieHeader];
        foreach ($headers as $header) {
            if (!is_string($header) || $header === '') {
                continue;
            }

            if (preg_match('/(?:^|;\s*)' . preg_quote($cookieName, '/') . '=([^;]+)/i', $header, $matches) === 1) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * @return int|string|null
     */
    private function normalizeIdentifier(mixed $value): int|string|null
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        $string = $this->stringValue($value);

        return $string !== null ? $string : null;
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
