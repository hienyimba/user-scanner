<?php

declare(strict_types=1);

// Exact-source compatibility patch for pinned PHP-Impersonate 2.0.0. Both
// pristine and already-patched files are verified before either is written.
$root = dirname(__DIR__, 2).'/vendor/hamaadraza/php-impersonate/src/';
$patches = [
    'Exception/RequestException.php' => [
        'sha256' => '7e7617ee0f36c512ed460e6bca0b11c7a6a0739a711ceb5405cb4c5c184bd45a',
        'replacements' => [
            '        private array $output = []' => "        private array \$output = [],\n        public readonly array \$diagnostics = []",
        ],
    ],
    'Ffi/CurlImpersonate.php' => [
        'sha256' => '11cefadccbf91ec7b1c3c7375d5a7eab0f957b0a5cf03af0d9a3c55dfafad0d2',
        'replacements' => [
            '    // libcurl option/info constants (stable ABI values from curl.h).' => <<<'PHP'
    // Capability marker checked by the scanner before enabling this transport.
    public const SCANNER_DIAGNOSTICS_VERSION = 1;

    private const CURLOPT_ERRORBUFFER = 10010;
    private const CURLINFO_OS_ERRNO = 2097177;
    private const CURLINFO_HTTP_CONNECTCODE = 2097174;
    private const CURLINFO_NAMELOOKUP_TIME = 3145732;
    private const CURLINFO_CONNECT_TIME = 3145733;
    private const CURLINFO_NUM_CONNECTS = 2097178;

    // libcurl option/info constants (stable ABI values from curl.h).
PHP,
            '        $slist = null;' => <<<'PHP'
        $slist = null;
        // Keep this allocation alive until the transfer and all error reads end.
        $errorBuffer = $ffi->new('char[256]');
PHP,
            '            $ffi->curl_easy_setopt($h, self::CURLOPT_URL, $url);' => <<<'PHP'
            $bufferCode = $ffi->curl_easy_setopt($h, self::CURLOPT_ERRORBUFFER, FFI::addr($errorBuffer[0]));
            if ($bufferCode !== self::CURLE_OK) {
                throw new RequestException('libcurl-impersonate: error diagnostics unavailable', $bufferCode);
            }
            $ffi->curl_easy_setopt($h, self::CURLOPT_URL, $url);
PHP,
            '                throw new RequestException("libcurl-impersonate request failed ($rc): $msg", $rc);' => <<<'PHP'
                throw new RequestException("libcurl-impersonate request failed ($rc): $msg", $rc,
                    diagnostics: $this->failureDiagnostics($h, $rc, FFI::string($errorBuffer), $curlOptions));
PHP,
            '            $capture->reset();' => <<<'PHP'
            // The handle outlives the stack allocation; never retain its pointer.
            $ffi->curl_easy_setopt($h, self::CURLOPT_ERRORBUFFER, null);
            $capture->reset();
PHP,
            '    private function applyMethod($h, string $method, ?string $body): void' => <<<'PHP'
    private function failureDiagnostics($h, int $code, string $buffer, array $options): array
    {
        $long = function (int $info) use ($h): ?int {
            $value = $this->ffi->new('long');

            return $this->ffi->curl_easy_getinfo($h, $info, FFI::addr($value)) === self::CURLE_OK
                ? (int) $value->cdata : null;
        };
        $time = function (int $info) use ($h): ?float {
            $value = $this->ffi->new('double');
            if ($this->ffi->curl_easy_getinfo($h, $info, FFI::addr($value)) !== self::CURLE_OK) {
                return null;
            }

            return is_finite((float) $value->cdata) ? round((float) $value->cdata * 1000, 3) : null;
        };
        $route = ($options['proxy'] ?? '') !== '' ? 'proxy' : 'direct';
        $connectStatus = $long(self::CURLINFO_HTTP_CONNECTCODE);
        $connectTime = $time(self::CURLINFO_CONNECT_TIME);
        // The raw buffer can contain proxy credentials, target names and URLs.
        // It is inspected here only and never attached to an exception or log.
        $text = strtolower($buffer);
        $detail = match (true) {
            $connectStatus === 407 => 'proxy_authentication_failed',
            str_contains($text, 'refused') => 'connection_refused',
            str_contains($text, 'timed out'), str_contains($text, 'timeout') => 'timed_out',
            $code === 5 => 'proxy_dns_failure',
            $code === 6 => 'host_dns_failure',
            $connectStatus !== null && $connectStatus >= 300 => 'proxy_tunnel_failed',
            str_contains($text, 'ssl'), str_contains($text, 'tls') => 'tls_handshake_failed',
            $code === 7 => 'connection_failed',
            $buffer === '' => 'unavailable',
            default => 'other',
        };
        $phase = match (true) {
            in_array($code, [5, 6], true) => 'name_lookup',
            $connectStatus !== null && $connectStatus >= 300 => 'target_connect',
            $code === 7 && $route === 'direct' => 'direct_connect',
            $code === 7 && $route === 'proxy' && $connectStatus === 0 && $connectTime === 0.0 => 'proxy_connect',
            $code === 7 => 'unknown_connect',
            in_array($code, [35, 51, 58, 60, 77, 80, 83, 90, 91], true) => 'tls',
            $code === 28 && ($connectTime === null || $connectTime === 0.0) => 'unknown_connect',
            default => 'transfer',
        };

        return ['curl_code' => $code, 'os_errno' => $long(self::CURLINFO_OS_ERRNO),
            'proxy_connect_status' => $connectStatus, 'connect_time_ms' => $connectTime,
            'lookup_time_ms' => $time(self::CURLINFO_NAMELOOKUP_TIME),
            'num_connects' => $long(self::CURLINFO_NUM_CONNECTS),
            'route' => $route, 'failure_phase' => $phase, 'detail' => $detail];
    }

    private function applyMethod($h, string $method, ?string $body): void
PHP,
        ],
    ],
];
$writes = [];
foreach ($patches as $relative => $patch) {
    $path = $root.$relative;
    if (! is_file($path)) {
        throw new RuntimeException('Pinned PHP-Impersonate diagnostics dependency is missing');
    }
    $source = file_get_contents($path);
    $replacements = $patch['replacements'];
    $canonical = str_replace(array_values($replacements), array_keys($replacements), $source);
    if (hash('sha256', $canonical) !== $patch['sha256']) {
        throw new RuntimeException('PHP-Impersonate '.$relative.' differs from audited 2.0.0 source; refusing diagnostics patch');
    }
    $patched = $canonical;
    foreach ($replacements as $needle => $addition) {
        if (substr_count($patched, $needle) !== 1) {
            throw new RuntimeException('Ambiguous PHP-Impersonate diagnostics patch');
        }
        $patched = str_replace($needle, $addition, $patched);
    }
    if ($source !== $patched) {
        $writes[$path] = $patched;
    }
}
foreach ($writes as $path => $source) {
    if (file_put_contents($path, $source) !== strlen($source)) {
        throw new RuntimeException('Could not install PHP-Impersonate diagnostics patch');
    }
}
echo $writes === [] ? "PHP-Impersonate native diagnostics already installed.\n" : "Installed audited PHP-Impersonate native diagnostics.\n";
