<?php

declare(strict_types=1);

require __DIR__.'/AcceptanceRuntime.php';

// Synthetic localhost-only native acceptance; no targets/proxies/secrets.
$runtime = null;
$exitCode = 0;
try {
    $options = ScannerAcceptanceRuntime::options($argv, true);
    if ($options['help']) {
        fwrite(STDOUT, 'Usage: php acceptance.php [--php PATH] [--workers 1..64] [--output PATH]'.PHP_EOL);
    } else {
        $runtime = new ScannerAcceptanceRuntime($options['php']);
        $results = $runtime->run(__DIR__.'/acceptance-worker.php', $options['workers'], 180);
        foreach ($results as $result) {
            if (! is_array($result['checks'] ?? null) || $result['checks'] === []
                || array_filter($result['checks'], static fn ($passed): bool => $passed !== true)) {
                throw new RuntimeException('Native acceptance checks failed');
            }
        }
        $report = ['transport' => 'ffi', 'workers' => $options['workers'], 'all_passed' => true, 'results' => $results];
        $flags = JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION;
        if ($options['output'] !== null
            && file_put_contents($options['output'], json_encode($report, $flags | JSON_PRETTY_PRINT).PHP_EOL) === false) {
            throw new RuntimeException('Cannot write acceptance report: '.$options['output']);
        }
        fwrite(STDOUT, json_encode($report, $flags).PHP_EOL);
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    $exitCode = 1;
} finally {
    $runtime?->close();
}
exit($exitCode);
