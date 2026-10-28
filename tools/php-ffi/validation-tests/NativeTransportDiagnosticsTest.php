<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Scanner\BrowserTransportSession;
use App\Services\Scanner\NativeTransportException;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class NativeTransportDiagnosticsTest extends TestCase
{
    public function test_numeric_diagnostics_survive_session_redaction_without_retaining_source_exception(): void
    {
        $target = 'private+control@example.test';
        $diagnostics = ['curl_code' => 7, 'os_errno' => 10061, 'proxy_connect_status' => 0,
            'connect_time_ms' => 0.0, 'lookup_time_ms' => 0.12345, 'num_connects' => 0,
            'route' => 'proxy', 'failure_phase' => 'proxy_connect', 'detail' => 'connection_refused'];
        try {
            (new BrowserTransportSession)->send(['url' => 'https://example.test', 'redact_values' => [$target]],
                new CookieJar, static function () use ($target, $diagnostics) {
                    throw new NativeTransportException($target.' '.rawurlencode($target).' https://user:password@example.test/token', $diagnostics);
                });
            $this->fail('Expected native transport failure');
        } catch (NativeTransportException $error) {
            $this->assertSame(7, $error->diagnostics['curl_code']);
            $this->assertSame('proxy_connect', $error->diagnostics['failure_phase']);
            $this->assertSame(0.123, $error->diagnostics['lookup_time_ms']);
            $this->assertNull($error->getPrevious());
            foreach ([$target, rawurlencode($target), 'password', '/token'] as $secret) {
                $this->assertStringNotContainsString($secret, $error->getMessage());
            }
        }
    }

    public function test_untrusted_diagnostic_strings_and_nonfinite_measurements_are_discarded(): void
    {
        $error = new NativeTransportException('Safe failure', ['curl_code' => 'private@example.test',
            'os_errno' => -1, 'proxy_connect_status' => 503, 'lookup_time_ms' => INF,
            'connect_time_ms' => NAN, 'num_connects' => 1, 'route' => 'https://user:password@example.test',
            'failure_phase' => 'private@example.test', 'detail' => 'raw proxy and target text', 'error_buffer' => 'secret']);
        $this->assertNull($error->diagnostics['curl_code']);
        $this->assertNull($error->diagnostics['os_errno']);
        $this->assertNull($error->diagnostics['lookup_time_ms']);
        $this->assertNull($error->diagnostics['connect_time_ms']);
        $this->assertSame(503, $error->diagnostics['proxy_connect_status']);
        $this->assertSame('unknown', $error->diagnostics['route']);
        $this->assertSame('unknown', $error->diagnostics['failure_phase']);
        $this->assertSame('unavailable', $error->diagnostics['detail']);
        $this->assertArrayNotHasKey('error_buffer', $error->diagnostics);
    }

    public function test_patch_is_idempotent_refuses_unknown_source_and_recovers_after_restore(): void
    {
        $fixture = storage_path('framework/testing/native-diagnostics-'.bin2hex(random_bytes(6)));
        $files = ['Ffi/CurlImpersonate.php', 'Exception/RequestException.php'];
        $vendor = $fixture.'/vendor/hamaadraza/php-impersonate/src/';
        File::ensureDirectoryExists($fixture.'/tools/php-ffi');
        $script = $fixture.'/tools/php-ffi/install-native-diagnostics.php';
        File::copy(base_path('tools/php-ffi/install-native-diagnostics.php'), $script);
        foreach ($files as $file) {
            File::ensureDirectoryExists(dirname($vendor.$file));
            File::copy(base_path('vendor/hamaadraza/php-impersonate/src/'.$file), $vendor.$file);
        }
        $run = static function () use ($script): Process {
            $process = new Process([PHP_BINARY, '-d', 'xdebug.mode=off', $script]);
            $process->run();

            return $process;
        };
        try {
            $before = array_map(static fn ($file) => hash_file('sha256', $vendor.$file), $files);
            $this->assertTrue($run()->isSuccessful());
            $this->assertTrue($run()->isSuccessful());
            $this->assertSame($before, array_map(static fn ($file) => hash_file('sha256', $vendor.$file), $files));
            $original = file_get_contents($vendor.$files[0]);
            file_put_contents($vendor.$files[0], $original."\n// unreviewed modification\n");
            $corrupt = array_map(static fn ($file) => hash_file('sha256', $vendor.$file), $files);
            $refused = $run();
            $this->assertFalse($refused->isSuccessful());
            $this->assertStringContainsString('refusing diagnostics patch', $refused->getOutput().$refused->getErrorOutput());
            $this->assertSame($corrupt, array_map(static fn ($file) => hash_file('sha256', $vendor.$file), $files));
            file_put_contents($vendor.$files[0], $original);
            $this->assertTrue($run()->isSuccessful());
            $this->assertSame($before, array_map(static fn ($file) => hash_file('sha256', $vendor.$file), $files));
        } finally {
            File::deleteDirectory($fixture);
        }
    }
}
