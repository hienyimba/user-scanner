<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final class DropboxEmailValidator extends BaseGeneratedValidator
{
    private const SHOW_URL = 'https://www.dropbox.com';

    private const CHECK_URL = self::SHOW_URL.'/2/account/check_user_with_email_exists';

    public function key(): string
    {
        return 'dropbox';
    }

    public function category(): string
    {
        return 'hosting';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Dropbox';
    }

    public function siteUrl(): string
    {
        return self::SHOW_URL;
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $startedAt = microtime(true);
        $cookies = new CookieJar;

        try {
            $request = Http::timeout(15)
                ->withOptions([
                    'allow_redirects' => true,
                    'cookies' => $cookies,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:150.0) Gecko/20100101 Firefox/150.0',
                    'Referer' => self::SHOW_URL,
                    'Origin' => self::SHOW_URL,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]);

            if (! empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $bootstrap = $request->get(self::SHOW_URL);
            if ($blocked = $this->detectBlockedOrChallenged($bootstrap)) {
                return $this->result($target, $blocked[0], $blocked[1], [], $options, $bootstrap, $startedAt);
            }
            if ($bootstrap->status() !== 200) {
                return $this->result($target, 'Error', 'Failed to access Dropbox (HTTP '.$bootstrap->status().')', [], $options, $bootstrap, $startedAt);
            }

            $csrf = $cookies->getCookieByName('t')?->getValue()
                ?? $this->extractCookieValue($bootstrap->header('Set-Cookie'), 't');
            if ($csrf === null) {
                return $this->result($target, 'Error', 'Dropbox CSRF token not retrieved', [], $options, $bootstrap, $startedAt);
            }

            $response = $request
                ->withHeaders(['X-CSRF-Token' => $csrf])
                ->post(self::CHECK_URL, ['email' => $target]);

            if ($blocked = $this->detectBlockedOrChallenged($response)) {
                return $this->result($target, $blocked[0], $blocked[1], [], $options, $response, $startedAt);
            }
            $data = $response->json();
            if ($response->status() !== 200) {
                $reason = is_array($data)
                    ? (string) (data_get($data, 'error.error_summary') ?? $data['error_summary'] ?? '')
                    : '';

                return $this->result($target, 'Error', 'Dropbox returned HTTP '.$response->status().($reason !== '' ? ': '.$reason : ''), [], $options, $response, $startedAt);
            }

            if (! is_array($data)
                || ! is_bool($data['exists'] ?? null)
                || ! is_bool($data['exists_as_recovery_email'] ?? null)
                || ! is_bool($data['has_passkey'] ?? null)) {
                return $this->result($target, 'Error', 'Dropbox response structure changed', [], $options, $response, $startedAt);
            }

            $exists = (bool) $data['exists'];
            $recovery = (bool) $data['exists_as_recovery_email'];
            $metadata = [
                'account_exists' => $exists || $recovery,
                'exists_as_recovery_email' => $recovery,
                'has_passkey' => (bool) $data['has_passkey'],
                'sources' => ['dropbox_csrf_cookie', 'dropbox_check_user_api'],
                'sensitive_fields' => ['has_passkey'],
            ];

            return $this->result($target, $exists || $recovery ? 'Registered' : 'Not Registered', '', $metadata, $options, $response, $startedAt);
        } catch (Throwable $e) {
            return $this->result($target, 'Error', $e->getMessage(), [], $options, null, $startedAt);
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Dropbox requires its CSRF bootstrap request'];
    }

    /** @param array<string, mixed> $metadata @param array<string, mixed> $options */
    private function result(string $target, string $status, string $reason, array $metadata, array $options, ?Response $response, float $startedAt): ScanResult
    {
        $metadata = $this->mergeRequestDiagnostics($metadata, $options, $response, $startedAt);

        return new ScanResult(
            target: $target,
            category: $this->category(),
            siteName: $this->siteName(),
            url: $this->siteUrl(),
            status: $status,
            reason: $reason,
            extra: $this->metadataSummary($metadata),
            mode: $this->mode(),
            key: $this->key(),
            metadata: $metadata,
            error: $status === 'Error' ? $reason : null,
        );
    }

    private function extractCookieValue(array|string|null $cookieHeader, string $name): ?string
    {
        $headers = is_array($cookieHeader) ? $cookieHeader : [$cookieHeader];
        foreach ($headers as $header) {
            if (is_string($header)
                && preg_match('/(?:^|[,;]\\s*)'.preg_quote($name, '/').'=([^;,]+)/i', $header, $matches) === 1) {
                return trim($matches[1]);
            }
        }

        return null;
    }
}
