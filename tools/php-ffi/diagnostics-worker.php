<?php

declare(strict_types=1);

use App\Services\Scanner\NativeTransportException;
use App\Services\Scanner\PhpFfiTransport;
use GuzzleHttp\Cookie\CookieJar;

require __DIR__.'/bootstrap-acceptance.php';
config(['scanner.php_ffi.enabled' => true, 'scanner.browser_transport.allow_loopback' => true]);
$native = new PhpFfiTransport;
$port = (int) $argv[1];
$closedPort = (int) $argv[2];
$target = 'synthetic+private@example.test';
$checks = [];
$measurements = [];
$check = static function (string $name, bool $passed) use (&$checks): void {
    $checks[$name] = $passed;
    if (! $passed) {
        throw new RuntimeException('Diagnostics acceptance failed: '.$name);
    }
};
foreach ([
    'direct' => ['url' => 'http://127.0.0.1:'.$closedPort.'/'.rawurlencode($target)],
    'proxy' => ['url' => 'https://synthetic-target.invalid/'.rawurlencode($target),
        'proxy' => 'http://synthetic-user:synthetic-password@127.0.0.1:'.$closedPort],
    'tunnel' => ['url' => 'https://synthetic-target.invalid/'.rawurlencode($target),
        'proxy' => 'http://synthetic-user:synthetic-password@127.0.0.1:'.$port],
] as $name => $spec) {
    try {
        $native->send($spec + ['method' => 'GET', 'timeout' => 3, 'redact_values' => [$target]], new CookieJar);
        $check($name.'_failed', false);
    } catch (NativeTransportException $error) {
        $data = $error->diagnostics;
        $measurements[$name] = $data;
        $check($name.'_numeric_code', is_int($data['curl_code']));
        $check($name.'_lookup_time', is_float($data['lookup_time_ms']));
        $check($name.'_connect_time', is_float($data['connect_time_ms']));
        $check($name.'_connect_count', is_int($data['num_connects']));
        $check($name.'_no_exception_chain', $error->getPrevious() === null);
        $serialized = json_encode([$error->getMessage(), $data], JSON_THROW_ON_ERROR);
        foreach ([$target, rawurlencode($target), 'synthetic-user', 'synthetic-password', 'synthetic-target.invalid'] as $secret) {
            $check($name.'_redaction_'.count($checks), ! str_contains($serialized, $secret));
        }
        if ($name === 'tunnel') {
            $check('tunnel_http_status', $data['proxy_connect_status'] === 503);
            $check('tunnel_phase', $data['failure_phase'] === 'target_connect');
            $check('tunnel_detail', $data['detail'] === 'proxy_tunnel_failed');
        } else {
            $check($name.'_curl7', $data['curl_code'] === 7);
            $check($name.'_errno', is_int($data['os_errno']) && $data['os_errno'] > 0);
            $check($name.'_connect_zero', $data['connect_time_ms'] === 0.0);
            $check($name.'_phase', $data['failure_phase'] === ($name === 'proxy' ? 'proxy_connect' : 'direct_connect'));
        }
    }
    $check($name.'_recovery', $native->send(['url' => 'http://127.0.0.1:'.$port.'/ok',
        'method' => 'GET', 'timeout' => 3], new CookieJar)->body() === 'ok');
}
echo json_encode(['all_passed' => ! in_array(false, $checks, true), 'checks' => $checks, 'measurements' => $measurements], JSON_THROW_ON_ERROR);
