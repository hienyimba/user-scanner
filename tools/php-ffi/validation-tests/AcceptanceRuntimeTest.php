<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ScannerAcceptanceRuntime;

require_once dirname(__DIR__).'/AcceptanceRuntime.php';

final class AcceptanceRuntimeTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_collects_each_worker_and_releases_both_fixture_ports(): void
    {
        $worker = $this->worker(<<<'PHP'
echo json_encode(['checks' => ['fixture_worker' => true], 'port' => (int) $argv[1],
    'closed_port' => (int) $argv[2], 'pid' => getmypid()], JSON_THROW_ON_ERROR);
PHP);
        $runtime = new ScannerAcceptanceRuntime(PHP_BINARY);
        $port = $runtime->port;
        $closedPort = $runtime->closedPort;

        try {
            $results = $runtime->run($worker, 3, 10);
            $this->assertCount(3, $results);
            $this->assertCount(3, array_unique(array_column($results, 'pid')));
            foreach ($results as $result) {
                $this->assertSame(['fixture_worker' => true], $result['checks']);
                $this->assertSame($port, $result['port']);
                $this->assertSame($closedPort, $result['closed_port']);
                $this->assertGreaterThanOrEqual(0, $result['duration_seconds']);
            }
        } finally {
            $runtime->close();
            $runtime->close();
        }

        $this->assertPortReleased($port);
        $this->assertPortReleased($closedPort);
    }

    public function test_slow_fixture_requests_do_not_block_other_requests_or_workers(): void
    {
        $worker = $this->worker(<<<'PHP'
$connect = static function (string $path) use ($argv) {
    $socket = stream_socket_client('tcp://127.0.0.1:'.$argv[1], $errno, $error, 3);
    if ($socket === false) {
        throw new RuntimeException($error, $errno);
    }
    stream_set_timeout($socket, 6);
    fwrite($socket, "GET ".$path." HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");

    return $socket;
};
$slowStarted = microtime(true);
$slow = $connect('/slow');
$start = microtime(true);
$fast = $connect('/ok');
$fastResponse = stream_get_contents($fast);
fclose($fast);
$fastSeconds = microtime(true) - $start;
$slowResponse = stream_get_contents($slow);
fclose($slow);
echo json_encode(['checks' => ['fast_finished_before_slow' => $fastSeconds < 1.2,
    'fast_response' => str_ends_with($fastResponse, "\r\n\r\nok"),
    'slow_response' => str_contains($slowResponse, '200 OK')], 'fast_seconds' => $fastSeconds,
    'slow_started' => $slowStarted, 'slow_finished' => microtime(true)], JSON_THROW_ON_ERROR);
PHP);
        $runtime = new ScannerAcceptanceRuntime(PHP_BINARY);

        try {
            $results = $runtime->run($worker, 2, 8);
            $this->assertCount(2, $results);
            foreach ($results as $result) {
                $this->assertNotContains(false, $result['checks'], json_encode($result, JSON_THROW_ON_ERROR));
            }
            $this->assertLessThan(min(array_column($results, 'slow_finished')),
                max(array_column($results, 'slow_started')), 'The slow requests from both workers should overlap.');
        } finally {
            $runtime->close();
        }
    }

    public function test_http_fixture_preserves_keepalive_until_a_request_closes_it(): void
    {
        $worker = $this->worker(<<<'PHP'
$socket = stream_socket_client('tcp://127.0.0.1:'.$argv[1], $errno, $error, 3);
if ($socket === false) {
    throw new RuntimeException($error, $errno);
}
stream_set_timeout($socket, 3);
$receive = static function () use ($socket): array {
    $status = trim((string) fgets($socket));
    $length = null;
    while (($line = fgets($socket)) !== false && $line !== "\r\n") {
        if (preg_match('/^Content-Length:\\s*(\\d+)/i', $line, $match)) {
            $length = (int) $match[1];
        }
    }
    if ($length === null) {
        throw new RuntimeException('Fixture response is missing its content length.');
    }
    $body = '';
    while (strlen($body) < $length) {
        $chunk = fread($socket, $length - strlen($body));
        if ($chunk === false || $chunk === '') {
            throw new RuntimeException('Fixture response ended before its declared content length.');
        }
        $body .= $chunk;
    }

    return ['status' => $status, 'body' => $body];
};
fwrite($socket, "GET /echo HTTP/1.1\r\nHost: 127.0.0.1\r\nCookie: first=one\r\n\r\n");
$first = $receive();
fwrite($socket, "GET /echo HTTP/1.1\r\nHost: 127.0.0.1\r\nCookie: second=two\r\nConnection: close\r\n\r\n");
$second = $receive();
$closed = fread($socket, 1) === '' && feof($socket);
fclose($socket);
echo json_encode(['checks' => ['first_response' => $first['status'] === 'HTTP/1.1 200 OK'
        && json_decode($first['body'], true, flags: JSON_THROW_ON_ERROR)['cookie'] === 'first=one',
    'second_response' => $second['status'] === 'HTTP/1.1 200 OK'
        && json_decode($second['body'], true, flags: JSON_THROW_ON_ERROR)['cookie'] === 'second=two',
    'explicit_close' => $closed]], JSON_THROW_ON_ERROR);
PHP);
        $runtime = new ScannerAcceptanceRuntime(PHP_BINARY);

        try {
            $results = $runtime->run($worker, 1, 8);
            $this->assertSame(['first_response' => true, 'second_response' => true, 'explicit_close' => true], $results[0]['checks']);
        } finally {
            $runtime->close();
        }
    }

    public function test_malformed_worker_json_fails_and_releases_fixture_ports(): void
    {
        $worker = $this->worker("echo 'fixture-invalid-json';");
        $runtime = new ScannerAcceptanceRuntime(PHP_BINARY);
        $port = $runtime->port;
        $closedPort = $runtime->closedPort;

        try {
            $error = $this->runFailure($runtime, $worker);
            $this->assertStringContainsString('JSON', $error->getMessage());
            $this->assertStringContainsString('fixture-invalid-json', $error->getMessage());
        } finally {
            $runtime->close();
        }

        $this->assertPortReleased($port);
        $this->assertPortReleased($closedPort);
    }

    public function test_worker_output_larger_than_a_pipe_buffer_is_collected_without_deadlock(): void
    {
        $worker = $this->worker(<<<'PHP'
fwrite(STDERR, str_repeat('fixture-log ', 16384));
echo json_encode(['payload' => str_repeat('x', 262144)], JSON_THROW_ON_ERROR);
PHP);
        $runtime = new ScannerAcceptanceRuntime(PHP_BINARY);

        try {
            $results = $runtime->run($worker, 2, 5);
            $this->assertCount(2, $results);
            foreach ($results as $result) {
                $this->assertSame(str_repeat('x', 262144), $result['payload']);
            }
        } finally {
            $runtime->close();
        }
    }

    public function test_nonzero_worker_exit_keeps_diagnostic_output(): void
    {
        $worker = $this->worker("echo '{}'; fwrite(STDERR, 'fixture-worker-error'); exit(23);");
        $runtime = new ScannerAcceptanceRuntime(PHP_BINARY);

        try {
            $error = $this->runFailure($runtime, $worker);
            $this->assertStringContainsString('23', $error->getMessage());
            $this->assertStringContainsString('fixture-worker-error', $error->getMessage());
        } finally {
            $runtime->close();
        }
    }

    public function test_timeout_terminates_a_worker_and_releases_fixture_ports(): void
    {
        $worker = $this->worker(<<<'PHP'
$heartbeat = 0;
while (true) {
    file_put_contents(__FILE__.'.heartbeat', (string) ++$heartbeat);
    usleep(25000);
}
PHP);
        $heartbeat = $worker.'.heartbeat';
        $this->temporaryFiles[] = $heartbeat;
        $runtime = new ScannerAcceptanceRuntime(PHP_BINARY);
        $port = $runtime->port;
        $closedPort = $runtime->closedPort;
        $started = microtime(true);

        try {
            $error = $this->runFailure($runtime, $worker, 0.75);
            $this->assertMatchesRegularExpression('/tim(?:e|ed)/i', $error->getMessage());
            $this->assertLessThan(3, microtime(true) - $started);
            $this->assertFileExists($heartbeat, 'The child must start before the timeout is tested.');
            $lastHeartbeat = file_get_contents($heartbeat);
            usleep(150000);
            $this->assertSame($lastHeartbeat, file_get_contents($heartbeat), 'The timed out child must stop running.');
        } finally {
            $runtime->close();
        }

        $this->assertPortReleased($port);
        $this->assertPortReleased($closedPort);
    }

    public function test_missing_worker_fails_without_leaking_fixture_ports(): void
    {
        $worker = sys_get_temp_dir().DIRECTORY_SEPARATOR.'missing acceptance '.bin2hex(random_bytes(8)).'.php';
        $runtime = new ScannerAcceptanceRuntime(PHP_BINARY);
        $port = $runtime->port;
        $closedPort = $runtime->closedPort;

        try {
            $this->runFailure($runtime, $worker);
        } finally {
            $runtime->close();
        }

        $this->assertPortReleased($port);
        $this->assertPortReleased($closedPort);
    }

    public function test_missing_php_executable_fails_without_leaking_fixture_ports(): void
    {
        $worker = $this->worker("echo '{\"checks\":{\"fixture_worker\":true}}';");
        $missingPhp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'missing php '.bin2hex(random_bytes(8));
        $runtime = new ScannerAcceptanceRuntime($missingPhp);
        $port = $runtime->port;
        $closedPort = $runtime->closedPort;

        try {
            $this->runFailure($runtime, $worker);
        } finally {
            $runtime->close();
        }

        $this->assertPortReleased($port);
        $this->assertPortReleased($closedPort);
    }

    public function test_options_accept_explicit_php_output_and_worker_count(): void
    {
        $options = ScannerAcceptanceRuntime::options(['acceptance.php', '--php', PHP_BINARY,
            '--output', 'report with spaces.json', '--workers', '2'], true);
        $this->assertSame(PHP_BINARY, $options['php']);
        $this->assertSame('report with spaces.json', $options['output']);
        $this->assertSame(2, $options['workers']);
    }

    public function test_options_reject_unknown_flags_missing_values_and_invalid_worker_counts(): void
    {
        foreach ([['--unknown'], ['--php'], ['--output'], ['--workers'], ['--workers', '0'],
            ['--workers', '-1'], ['--workers', '1.5'], ['--workers', 'nonsense']] as $arguments) {
            try {
                ScannerAcceptanceRuntime::options(['acceptance.php', ...$arguments], true);
                $this->fail('Invalid arguments were accepted: '.implode(' ', $arguments));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        ScannerAcceptanceRuntime::options(['diagnostics-acceptance.php', '--workers', '2']);
    }

    private function worker(string $source): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'scanner ffi acceptance '.bin2hex(random_bytes(8)).'.php';
        $this->temporaryFiles[] = $path;
        file_put_contents($path, "<?php\ndeclare(strict_types=1);\n".$source);

        return $path;
    }

    private function runFailure(ScannerAcceptanceRuntime $runtime, string $worker, float $timeout = 5): RuntimeException
    {
        try {
            $runtime->run($worker, 1, $timeout);
        } catch (RuntimeException $error) {
            return $error;
        }

        $this->fail('A failing worker must not be reported as successful.');
    }

    private function assertPortReleased(int $port): void
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:'.$port, $errno, $error);
        $this->assertIsResource($socket, 'Fixture port '.$port.' was not released: '.$error);
        if (is_resource($socket)) {
            fclose($socket);
        }
    }
}
