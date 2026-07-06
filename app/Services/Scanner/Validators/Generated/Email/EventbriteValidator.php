<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class EventbriteValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'eventbrite';
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
        return 'Eventbrite';
    }

    public function siteUrl(): string
    {
        return 'https://eventbrite.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $startedAt = microtime(true);
        $cookieJar = new CookieJar();

        try {
            $request = Http::timeout(5)
                ->withOptions([
                    'allow_redirects' => true,
                    'cookies' => $cookieJar,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->withHeaders([
                    'User-Agent' => (string) config('scanner.user_agent'),
                    'Accept' => '*/*',
                    'Accept-Language' => 'en,en-US;q=0.9',
                    'Referer' => 'https://www.eventbrite.com/signin/',
                    'Content-Type' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Origin' => 'https://www.eventbrite.com',
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $signin = $request->get('https://www.eventbrite.com/signin/');
            if ($blocked = $this->detectBlockedOrChallenged($signin)) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    $blocked[0],
                    $blocked[1],
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $signin, $startedAt),
                );
            }

            $csrfToken = $cookieJar->getCookieByName('csrftoken')?->getValue()
                ?? $this->extractCookieValue($signin->header('Set-Cookie'), 'csrftoken');

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
                    metadata: $this->mergeRequestDiagnostics([], $options, $signin, $startedAt),
                );
            }

            $response = $request->withHeaders([
                'X-CSRFToken' => $csrfToken,
            ])->post('https://www.eventbrite.com/api/v3/users/lookup/', [
                'email' => $target,
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

            $data = $response->json();
            if ($response->status() !== 200 || !is_array($data) || !array_key_exists('exists', $data)) {
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

            $status = $data['exists'] === true ? 'Registered' : 'Not Registered';
            $metadata = $status === 'Registered'
                ? $this->mergeRequestDiagnostics($this->buildStructuredMetadata($response, $target, $status), $options, $response, $startedAt)
                : $this->mergeRequestDiagnostics([], $options, $response, $startedAt);

            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                $this->siteUrl(),
                $status,
                '',
                mode: $this->mode(),
                key: $this->key(),
                metadata: $metadata,
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

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if (!in_array($status, ['Registered', 'Found'], true)) {
            return [];
        }

        $data = $response->json();
        if (!is_array($data)) {
            return [];
        }

        $metadata = [
            'sources' => ['api_json', 'eventbrite_lookup_api'],
            'account_exists' => true,
        ];

        $userId = $data['user_id'] ?? ($data['id'] ?? null);
        if (is_numeric($userId)) {
            $metadata['user_id'] = (int) $userId;
        } elseif (is_scalar($userId) && trim((string) $userId) !== '') {
            $metadata['user_id'] = trim((string) $userId);
        }

        foreach ([
            'can_login' => ['can_login', 'canLogin'],
            'is_email_verified' => ['is_email_verified', 'isEmailVerified', 'email_verified'],
            'is_organizer' => ['is_organizer', 'isOrganizer'],
            'mfa_enabled' => ['mfa_enabled', 'mfaEnabled'],
        ] as $targetKey => $sourceKeys) {
            foreach ($sourceKeys as $sourceKey) {
                if (is_bool($data[$sourceKey] ?? null)) {
                    $metadata[$targetKey] = (bool) $data[$sourceKey];
                    break;
                }
            }
        }

        $signInMethods = $data['sign_in_methods'] ?? ($data['signInMethods'] ?? ($data['login_methods'] ?? null));
        if (is_array($signInMethods)) {
            $normalized = [];
            foreach ($signInMethods as $method) {
                if (is_scalar($method) && trim((string) $method) !== '') {
                    $normalized[] = trim((string) $method);
                }
            }

            if ($normalized !== []) {
                $metadata['sign_in_methods'] = array_values(array_unique($normalized));
            }
        }

        $sensitiveFields = array_values(array_filter([
            isset($metadata['sign_in_methods']) ? 'sign_in_methods' : null,
            array_key_exists('mfa_enabled', $metadata) ? 'mfa_enabled' : null,
        ]));
        if ($sensitiveFields !== []) {
            $metadata['sensitive_fields'] = $sensitiveFields;
        }

        return $metadata;
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
}
