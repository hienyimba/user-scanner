<?php

declare(strict_types=1);

/** Loopback fixtures and concurrent PHP child processes; no application boot. */
final class ScannerAcceptanceRuntime
{
    public readonly int $port;

    public readonly int $closedPort;

    private mixed $server = null;

    private mixed $reserved = null;

    private array $clients = [];

    private array $children = [];

    private array $temporaryFiles = [];

    private ?string $temporaryDirectory = null;

    private const MAX_OUTPUT_BYTES = 8 * 1024 * 1024;

    public function __construct(private readonly string $phpBinary)
    {
        if ($phpBinary === '') {
            throw new InvalidArgumentException('PHP executable must not be empty');
        }
        try {
            $this->server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $error,
                STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, stream_context_create(['socket' => ['backlog' => 128]]));
            if ($this->server === false) {
                throw new RuntimeException('Cannot start loopback fixture server: '.$error);
            }
            stream_set_blocking($this->server, false);
            $this->port = $this->socketPort($this->server);
            // Bind but never listen: keep a genuinely refused port reserved for
            // diagnostics until all children have finished using it.
            $this->reserved = @stream_socket_server('tcp://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND);
            if ($this->reserved === false) {
                throw new RuntimeException('Cannot reserve refused fixture port: '.$error);
            }
            $this->closedPort = $this->socketPort($this->reserved);
        } catch (Throwable $error) {
            $this->close();
            throw $error;
        }
    }

    public static function options(array $arguments, bool $allowWorkers = false): array
    {
        $options = ['php' => PHP_BINARY, 'output' => null, 'workers' => 4, 'help' => false];
        $allowed = $allowWorkers ? ['php', 'output', 'workers'] : ['php', 'output'];
        for ($index = 1; $index < count($arguments); $index++) {
            $argument = $arguments[$index];
            if (in_array($argument, ['--help', '-h'], true)) {
                $options['help'] = true;

                continue;
            }
            if (! str_starts_with($argument, '--')) {
                throw new InvalidArgumentException('Unexpected argument: '.$argument);
            }
            [$name, $value] = array_pad(explode('=', substr($argument, 2), 2), 2, null);
            if (! in_array($name, $allowed, true)) {
                throw new InvalidArgumentException('Unknown option: --'.$name);
            }
            if ($value === null) {
                $value = $arguments[++$index] ?? null;
            }
            if (! is_string($value) || $value === '' || str_starts_with($value, '--')) {
                throw new InvalidArgumentException('Missing value for --'.$name);
            }
            if ($name === 'workers') {
                if (! ctype_digit($value) || (int) $value < 1 || (int) $value > 64) {
                    throw new InvalidArgumentException('--workers must be an integer between 1 and 64');
                }
                $value = (int) $value;
            }
            $options[$name] = $value;
        }

        return $options;
    }

    /** @return array<int, array<string, mixed>> */
    public function run(string $workerFile, int $workers, float $timeoutSeconds): array
    {
        try {
            if (! is_resource($this->server)) {
                throw new RuntimeException('Acceptance runtime is already closed');
            }
            if ($workers < 1 || $workers > 64 || ! is_finite($timeoutSeconds) || $timeoutSeconds <= 0) {
                throw new InvalidArgumentException('Invalid worker count or timeout');
            }
            if (! is_file($workerFile)) {
                throw new RuntimeException('Acceptance worker is missing: '.$workerFile);
            }
            $this->temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'scanner-ffi-acceptance-'.bin2hex(random_bytes(8));
            if (! mkdir($this->temporaryDirectory, 0700)) {
                throw new RuntimeException('Cannot create acceptance output directory');
            }
            for ($index = 0; $index < $workers; $index++) {
                $this->startWorker($workerFile, $index);
            }
            $results = [];
            while ($this->children !== []) {
                $this->serve();
                foreach ($this->children as $index => $child) {
                    $status = proc_get_status($child['process']);
                    $elapsed = (hrtime(true) - $child['started_ns']) / 1e9;
                    if ($status['running']) {
                        if ($elapsed > $timeoutSeconds) {
                            throw new RuntimeException('Acceptance worker '.$index.' timed out after '.$timeoutSeconds.' seconds');
                        }

                        continue;
                    }
                    // PHP <=8.2 can return the real exit code only once. Do not
                    // replace it with proc_close's later -1 result.
                    $exitCode = $status['exitcode'];
                    $closeCode = proc_close($child['process']);
                    unset($this->children[$index]);
                    $exitCode = $exitCode >= 0 ? $exitCode : $closeCode;
                    $stdout = $this->readOutput($child['stdout']);
                    $stderr = $this->readOutput($child['stderr']);
                    if ($exitCode !== 0) {
                        throw new RuntimeException('Acceptance worker '.$index.' failed with exit '.$exitCode.': '
                            .substr($stdout, 0, 1500).' '.substr($stderr, 0, 1500));
                    }
                    try {
                        $record = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
                    } catch (JsonException $error) {
                        throw new RuntimeException('Acceptance worker '.$index.' returned invalid JSON: '
                            .substr($stdout, 0, 1500).' '.substr($stderr, 0, 500), previous: $error);
                    }
                    if (! is_array($record) || $record === [] || array_is_list($record)) {
                        throw new RuntimeException('Acceptance worker '.$index.' returned a JSON value instead of a report object');
                    }
                    $record['duration_seconds'] = round($elapsed, 3);
                    $results[$index] = $record;
                }
            }
            ksort($results);

            return array_values($results);
        } finally {
            $this->close();
        }
    }

    private function startWorker(string $file, int $index): void
    {
        $stdout = $this->temporaryDirectory.DIRECTORY_SEPARATOR.$index.'.stdout';
        $stderr = $this->temporaryDirectory.DIRECTORY_SEPARATOR.$index.'.stderr';
        $this->temporaryFiles[] = $stdout;
        $this->temporaryFiles[] = $stderr;
        $command = [$this->phpBinary, '-d', 'ffi.enable=true', '-d', 'xdebug.mode=off'];
        if (PHP_OS_FAMILY === 'Windows') {
            $command = [...$command, '-d', 'extension=ffi'];
        }
        $command = [...$command, $file, (string) $this->port, (string) $this->closedPort];
        // Regular output files avoid anonymous-pipe buffer deadlocks and the
        // Windows restriction on selecting process pipes alongside sockets.
        $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $stdout, 'wb'],
            2 => ['file', $stderr, 'wb']], $pipes, dirname(__DIR__, 2), options: ['bypass_shell' => true]);
        if (! is_resource($process)) {
            throw new RuntimeException('Could not start acceptance PHP worker '.$index);
        }
        fclose($pipes[0]);
        $this->children[$index] = ['process' => $process, 'stdout' => $stdout, 'stderr' => $stderr, 'started_ns' => hrtime(true)];
    }

    private function readOutput(string $path): string
    {
        if (filesize($path) > self::MAX_OUTPUT_BYTES) {
            throw new RuntimeException('Acceptance worker output exceeded 8 MiB');
        }
        $text = file_get_contents($path);
        if ($text === false) {
            throw new RuntimeException('Cannot read acceptance worker output');
        }

        return $text;
    }

    private function serve(): void
    {
        $read = [$this->server];
        $write = [];
        $now = hrtime(true);
        foreach ($this->clients as $client) {
            $read[] = $client['socket'];
            if ($client['response'] !== null && $client['ready_ns'] <= $now) {
                $write[] = $client['socket'];
            }
        }
        $except = [];
        if (@stream_select($read, $write, $except, 0, 10000) === false) {
            throw new RuntimeException('Loopback fixture socket selection failed');
        }
        foreach ($read as $socket) {
            if ($socket === $this->server) {
                while (($accepted = @stream_socket_accept($this->server, 0)) !== false) {
                    stream_set_blocking($accepted, false);
                    $this->clients[(int) $accepted] = ['socket' => $accepted, 'request' => '',
                        'response' => null, 'offset' => 0, 'ready_ns' => 0, 'close_after_response' => false];
                }

                continue;
            }
            $key = (int) $socket;
            $chunk = @fread($socket, 8192);
            if ($chunk === false || ($chunk === '' && feof($socket))) {
                $this->closeClient($key);

                continue;
            }
            if ($this->clients[$key]['response'] !== null) {
                continue;
            }
            $this->clients[$key]['request'] .= $chunk;
            if (strlen($this->clients[$key]['request']) > 65536) {
                $this->closeClient($key);

                continue;
            }
            if (str_contains($this->clients[$key]['request'], "\r\n\r\n")) {
                $this->respond($key);
            }
        }
        foreach ($write as $socket) {
            $key = (int) $socket;
            if (! isset($this->clients[$key])) {
                continue;
            }
            $client = &$this->clients[$key];
            $written = @fwrite($socket, substr($client['response'], $client['offset']));
            if ($written === false) {
                unset($client);
                $this->closeClient($key);

                continue;
            }
            $client['offset'] += $written;
            $finished = $client['offset'] === strlen($client['response']);
            unset($client);
            if ($finished) {
                if ($this->clients[$key]['close_after_response']) {
                    $this->closeClient($key);
                } else {
                    // Preserve the original HTTP/1.1 server's connection reuse
                    // so the soak still exercises a persistent curl session.
                    $this->clients[$key]['request'] = '';
                    $this->clients[$key]['response'] = null;
                    $this->clients[$key]['offset'] = 0;
                    $this->clients[$key]['ready_ns'] = 0;
                }
            }
        }
    }

    private function respond(int $key): void
    {
        $lines = explode("\r\n", $this->clients[$key]['request']);
        [$method, $target] = array_pad(explode(' ', array_shift($lines), 3), 2, '');
        $headers = [];
        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }
        $path = parse_url($target, PHP_URL_PATH);
        $status = $method === 'CONNECT' ? '503 Service Unavailable' : '200 OK';
        $extra = ['Content-Type' => 'application/json'];
        $body = $method === 'CONNECT' ? '' : json_encode(['cookie' => $headers['cookie'] ?? ''], JSON_THROW_ON_ERROR);
        if ($method !== 'CONNECT') {
            if ($path === '/redirect') {
                $status = '302 Found';
                $extra += ['Location' => '/echo', 'Set-Cookie' => 'step=one; Path=/'];
            } elseif ($path === '/cross') {
                $status = '302 Found';
                $extra += ['Location' => 'http://localhost:'.$this->port.'/echo', 'Set-Cookie' => 'host=secret; Path=/'];
            } elseif ($path === '/large') {
                $body = str_repeat('x', 16384);
            } elseif ($path === '/ok') {
                $body = 'ok';
                $extra['Content-Type'] = 'text/plain';
            }
        }
        $close = $method === 'CONNECT' || strtolower($headers['connection'] ?? '') === 'close';
        $response = 'HTTP/1.1 '.$status."\r\nConnection: ".($close ? 'close' : 'keep-alive')."\r\nContent-Length: ".strlen($body)."\r\n";
        foreach ($extra as $name => $value) {
            $response .= $name.': '.$value."\r\n";
        }
        $this->clients[$key]['response'] = $response."\r\n".$body;
        $this->clients[$key]['ready_ns'] = hrtime(true) + ($path === '/slow' ? 2_000_000_000 : 0);
        $this->clients[$key]['close_after_response'] = $close;
    }

    private function socketPort(mixed $socket): int
    {
        $address = stream_socket_get_name($socket, false);
        if (! is_string($address) || ! preg_match('/:(\d+)$/', $address, $match)) {
            throw new RuntimeException('Cannot read loopback fixture port');
        }

        return (int) $match[1];
    }

    private function closeClient(int $key): void
    {
        if (isset($this->clients[$key])) {
            fclose($this->clients[$key]['socket']);
            unset($this->clients[$key]);
        }
    }

    public function close(): void
    {
        foreach ($this->children as $child) {
            @proc_terminate($child['process']);
        }
        $deadline = hrtime(true) + 1_000_000_000;
        foreach ($this->children as $child) {
            while (($status = proc_get_status($child['process']))['running'] && hrtime(true) < $deadline) {
                usleep(10000);
            }
            if ($status['running']) {
                @proc_terminate($child['process'], 9);
            }
            proc_close($child['process']);
        }
        $this->children = [];
        foreach (array_keys($this->clients) as $key) {
            $this->closeClient($key);
        }
        foreach (['server', 'reserved'] as $property) {
            if (is_resource($this->$property)) {
                fclose($this->$property);
            }
            $this->$property = null;
        }
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->temporaryFiles = [];
        if ($this->temporaryDirectory !== null && is_dir($this->temporaryDirectory)) {
            rmdir($this->temporaryDirectory);
        }
        $this->temporaryDirectory = null;
    }

    public function __destruct()
    {
        $this->close();
    }
}
