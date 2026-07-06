<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class CourseraValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'coursera';
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
        return 'Coursera';
    }

    public function siteUrl(): string
    {
        return 'https://coursera.org';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $startedAt = microtime(true);

        try {
            $request = Http::timeout(6)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36',
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
                'X-Coursera-Application' => 'front-page',
                'Origin' => 'https://www.coursera.org',
                'Referer' => 'https://www.coursera.org/?authMode=signup',
                'Accept-Encoding' => 'identity',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->post('https://www.coursera.org/api/userAccounts.v1?action=getLoginMethods&email=' . urlencode($target), []);
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

            if (!is_array($data) || !array_key_exists('loginMethods', $data)) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Error',
                    "Missing 'loginMethods' in response, report it on github",
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
                );
            }

            $methods = $data['loginMethods'];
            if (!is_array($methods)) {
                return new ScanResult(
                    $target,
                    $this->category(),
                    $this->siteName(),
                    $this->siteUrl(),
                    'Error',
                    'Unexpected data type for loginMethods: ' . gettype($methods) . ', report it on github',
                    mode: $this->mode(),
                    key: $this->key(),
                    metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt),
                );
            }

            if (count($methods) > 0) {
                $normalizedMethods = array_values(array_filter(array_map(
                    static fn (mixed $method): ?string => is_scalar($method) && trim((string) $method) !== '' ? trim((string) $method) : null,
                    $methods,
                )));
                $extra = $this->metadataSummary([
                    'Login methods' => $normalizedMethods,
                ]);
                $metadata = [
                    'public_email' => $target,
                    'account_exists' => true,
                    'login_methods' => $normalizedMethods,
                    'sensitive_fields' => ['login_methods'],
                    'sources' => ['api_json', 'login_methods_api'],
                ];

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
                    confidence: 0.86,
                    metadata: $this->mergeRequestDiagnostics($metadata, $options, $response, $startedAt),
                );
            }

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
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = str_contains($message, 'timed out')
                ? (str_contains($message, 'read') ? 'Server took too long to respond (Coursera)' : 'Connection timed out (Coursera)')
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
