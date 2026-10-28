<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use Illuminate\Http\Client\ConnectionException;

/** Only numeric measurements and fixed classifications may leave native curl. */
final class NativeTransportException extends ConnectionException
{
    public readonly array $diagnostics;

    public function __construct(string $message, array $diagnostics)
    {
        parent::__construct($message);
        $safe = [];
        foreach (['curl_code', 'os_errno', 'proxy_connect_status', 'num_connects'] as $key) {
            $value = $diagnostics[$key] ?? null;
            $safe[$key] = is_int($value) && $value >= 0 ? $value : null;
        }
        foreach (['connect_time_ms', 'lookup_time_ms'] as $key) {
            $value = $diagnostics[$key] ?? null;
            $safe[$key] = (is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= 0
                ? round((float) $value, 3) : null;
        }
        foreach ([
            'route' => ['direct', 'proxy'],
            'failure_phase' => ['proxy_connect', 'target_connect', 'unknown_connect', 'direct_connect', 'name_lookup', 'tls', 'transfer', 'unknown'],
            'detail' => ['connection_refused', 'timed_out', 'proxy_dns_failure', 'host_dns_failure', 'proxy_tunnel_failed',
                'proxy_authentication_failed', 'connection_failed', 'tls_handshake_failed', 'other', 'unavailable'],
        ] as $key => $allowed) {
            $value = $diagnostics[$key] ?? null;
            $safe[$key] = in_array($value, $allowed, true) ? $value : ($key === 'detail' ? 'unavailable' : 'unknown');
        }
        $this->diagnostics = $safe;
    }
}
