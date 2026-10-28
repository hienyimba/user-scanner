<?php

declare(strict_types=1);

use App\Services\Scanner\PhpFfiTransport;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Raza\PHPImpersonate\PHPImpersonate;

require __DIR__.'/bootstrap-acceptance.php';
config(['scanner.browser_transport.allow_loopback' => true,
    'scanner.browser_transport.max_body_bytes' => 4096, 'scanner.php_ffi.enabled' => true]);
$base = 'http://127.0.0.1:'.(int) $argv[1];
$transport = new PhpFfiTransport;
$checks = [];
$check = static function ($name, $condition) use (&$checks) {
    $checks[$name] = (bool) $condition;
    if (! $condition) {
        throw new RuntimeException('Acceptance failed: '.$name);
    }
};
$send = static fn ($path, $jar, $extra = []) => $transport->send($extra + ['url' => $base.$path, 'method' => 'GET', 'timeout' => 3], $jar);
$jar = new CookieJar;
$response = $send('/redirect', $jar);
$check('intermediate_cookie_followed', $response->json('cookie') === 'step=one');
$check('intermediate_cookie_retained', $send('/echo', $jar)->json('cookie') === 'step=one');
$response = $send('/cross', new CookieJar, ['allowed_redirect_hosts' => ['localhost']]);
$check('cross_host_cookie_not_forwarded', $response->json('cookie') === '');
try {
    $send('/slow', new CookieJar, ['timeout' => 1]);
    $check('timeout', false);
} catch (ConnectionException) {
    $check('timeout', true);
}
try {
    $send('/large', new CookieJar);
    $check('body_limit', false);
} catch (ConnectionException) {
    $check('body_limit', true);
}
$check('reuse_after_failures', $send('/echo', new CookieJar)->status() === 200);
// An inherited proxy must not capture an explicitly direct request.
putenv('http_proxy=http://127.0.0.1:1');
putenv('HTTP_PROXY=http://127.0.0.1:1');
putenv('NO_PROXY=');
$check('explicit_direct_ignores_environment_proxy', $send('/echo', new CookieJar)->status() === 200);
putenv('http_proxy');
putenv('HTTP_PROXY');
putenv('NO_PROXY');
$iterations = 1000;
$heapStart = memory_get_usage(true);
for ($i = 0; $i < $iterations; $i++) {
    $check('soak', $send('/echo', new CookieJar)->status() === 200);
    if ($i % 250 === 249) {
        PHPImpersonate::closeFfiEngines();
    }
}
$check('heap_growth_under_8mib', memory_get_usage(true) - $heapStart < 8 * 1024 * 1024);
$check('close_and_recreate', (function () use ($send) {
    PHPImpersonate::closeFfiEngines();

    return $send('/echo', new CookieJar)->status() === 200;
})());
if (function_exists('pcntl_fork')) {
    $pid = pcntl_fork();
    if ($pid === 0) {
        exit($send('/echo', new CookieJar)->status() === 200 ? 0 : 1);
    }
    pcntl_waitpid($pid, $status);
    $check('fork_handle_reinitialization', pcntl_wexitstatus($status) === 0 && $send('/echo', new CookieJar)->status() === 200);
}
echo json_encode(['checks' => $checks, 'iterations' => $iterations, 'php' => PHP_VERSION,
    'os' => PHP_OS_FAMILY, 'heap_peak_bytes' => memory_get_peak_usage(true), 'heap_growth_bytes' => memory_get_usage(true) - $heapStart], JSON_THROW_ON_ERROR);
