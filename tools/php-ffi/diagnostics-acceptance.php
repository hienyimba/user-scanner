<?php

declare(strict_types=1);

require __DIR__.'/AcceptanceRuntime.php';

// Synthetic loopback-only native failure diagnostics and recovery fixtures.
$runtime = null;
$exitCode = 0;

try {
    $options = ScannerAcceptanceRuntime::options($argv);
    if ($options['help']) {
        fwrite(STDOUT, 'Usage: php diagnostics-acceptance.php [--php PATH] [--output PATH]'.PHP_EOL);
    } else {
        $runtime = new ScannerAcceptanceRuntime($options['php']);
        $results = $runtime->run(__DIR__.'/diagnostics-worker.php', 1, 30);
        $report = $results[0];
        unset($report['duration_seconds']);

        if (($report['all_passed'] ?? false) !== true || ! is_array($report['checks'] ?? null)
            || $report['checks'] === [] || count(array_filter($report['checks'], static fn ($passed): bool => $passed !== true)) > 0) {
            throw new RuntimeException('Synthetic diagnostics checks failed.');
        }

        $jsonFlags = JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION;
        if ($options['output'] !== null) {
            if (file_put_contents($options['output'], json_encode($report, $jsonFlags | JSON_PRETTY_PRINT).PHP_EOL) === false) {
                throw new RuntimeException('Could not write diagnostics report to '.$options['output']);
            }
        }

        fwrite(STDOUT, json_encode($report, $jsonFlags).PHP_EOL);
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    $exitCode = 1;
} finally {
    $runtime?->close();
}

exit($exitCode);
