<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Raza\PHPImpersonate\Exception\RequestException;
use Raza\PHPImpersonate\Ffi\CurlImpersonate;
use Raza\PHPImpersonate\Ffi\LibResolver;
use Raza\PHPImpersonate\PHPImpersonate;
use Raza\PHPImpersonate\Request;
use Raza\PHPImpersonate\Support\CurlOptions;

/** Synchronous, non-reentrant native transport for ordinary queue workers. */
class PhpFfiTransport
{
    private static ?bool $verifiedLibrary = null;

    private static bool $inFlight = false;

    public static function selected(string $mode, string $key): bool
    {
        return $mode === 'email' && (bool) config('scanner.php_ffi.enabled', false)
            && in_array($key, (array) config('scanner.php_ffi.email_modules', []), true);
    }

    public function isAvailable(): bool
    {
        $ca = trim((string) config('scanner.php_ffi.ca_bundle', ''));
        if ($ca !== '' && ! is_readable($ca)) {
            return false;
        }
        if (! class_exists(PHPImpersonate::class) || ! CurlOptions::isAllowed('connect-timeout')
            || ! CurlOptions::emptyIsMeaningful('noproxy')
            || ! defined(CurlImpersonate::class.'::SCANNER_DIAGNOSTICS_VERSION')
            || CurlImpersonate::SCANNER_DIAGNOSTICS_VERSION !== 1) {
            return false;
        }
        if (self::$verifiedLibrary === null) {
            $library = LibResolver::resolve();
            // Only the audited bundled binaries; no arbitrary env override or
            // opportunistic download. Linux stripped and upstream hashes differ.
            self::$verifiedLibrary = $library !== null && in_array(hash_file('sha256', $library), [
                '70adc4d9b16b25ba6b7778922d84043b479dc168caf5f43837f48d4294aae751',
                'd042c782883ac6f3f9f2bf24267de3a5c8ea4bf3a8a0adfee069ee8bcb4d34ad',
                '803a80e7af47af41afbe10763d6365fc323c6e65a0bdeb2fcc3af0225e78e4cd',
            ], true);
        }

        return self::$verifiedLibrary && PHPImpersonate::ffiAvailable();
    }

    public function send(array $spec, CookieJar $jar): Response
    {
        return app(BrowserTransportSession::class)->send($spec, $jar, fn (array $hop) => $this->singleHop($hop));
    }

    /** Single hop; production callers must use send() for session policy. */
    public function singleHop(array $spec): Response
    {
        if (! $this->isAvailable()) {
            throw new ConnectionException('PHP FFI transport unavailable: enable FFI and install the pinned compatibility patches');
        }
        $headers = (array) ($spec['headers'] ?? []);
        $body = $spec['body'] ?? null;
        if ($body !== null && ! in_array($spec['method'], ['GET', 'HEAD'], true)) {
            if (($spec['body_mode'] ?? 'json') === 'form') {
                $body = http_build_query(is_array($body) ? $body : []);
                $headers['Content-Type'] ??= 'application/x-www-form-urlencoded';
            } elseif (($spec['body_mode'] ?? 'json') === 'json') {
                $body = json_encode($body, JSON_THROW_ON_ERROR);
                $headers['Content-Type'] ??= 'application/json';
            } elseif (! is_string($body)) {
                $body = json_encode($body, JSON_THROW_ON_ERROR);
            }
        } else {
            $body = null;
        }
        $options = ['proxy' => $spec['proxy'] ?? '', 'noproxy' => '', 'max-redirs' => 0,
            'connect-timeout' => max(1, (int) ceil($spec['connect_timeout'] ?? 10)),
            'max-filesize' => max(1024, (int) config('scanner.browser_transport.max_body_bytes', 8388608))];
        $ca = trim((string) config('scanner.php_ffi.ca_bundle', ''));
        if ($ca !== '') {
            if (! is_readable($ca)) {
                throw new ConnectionException('Configured FFI CA bundle is not readable');
            }
            $options['cacert'] = $ca;
        }
        // Audited library accepts integer seconds. At most <1s rounding, with
        // a monotonic late-response check and a reserved queue cleanup margin.
        $client = new PHPImpersonate(browser: (string) ($spec['impersonate'] ?? 'chrome131'),
            timeout: max(1, (int) ceil($spec['timeout'] ?? 15)), curlOptions: $options,
            engine: PHPImpersonate::ENGINE_FFI);
        if (self::$inFlight) {
            throw new ConnectionException('Reentrant FFI requests are not supported; use ordinary queue worker processes');
        }
        self::$inFlight = true;
        try {
            $response = $client->send(new Request($spec['method'], $spec['url'], $headers, $body));
        } catch (RequestException $error) {
            throw new NativeTransportException(
                ScannerDiagnostics::redact($error->getMessage(), (array) ($spec['redact_values'] ?? [])),
                $error->diagnostics + ['curl_code' => (int) $error->getCode(),
                    'route' => ($spec['proxy'] ?? '') !== '' ? 'proxy' : 'direct'],
            );
        } finally {
            self::$inFlight = false;
        }
        $responseHeaders = $response->headers();
        $responseHeaders['Set-Cookie'] = $response->setCookieHeaders();

        return new Response(new PsrResponse($response->status(), $responseHeaders, $response->body()));
    }
}
