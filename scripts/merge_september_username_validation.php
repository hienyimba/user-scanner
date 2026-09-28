<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$records = [];

foreach (range(1, 8) as $shard) {
    mergeReport($root."/storage/app/september-username-validation-shard-{$shard}.json", $records);
}

// These reruns were made after correcting the request and response contracts.
mergeReport($root.'/storage/app/september-username-validation-review.json', $records);
mergeReport($root.'/storage/app/september-username-validation-dblp.json', $records);
ksort($records);

$summary = array_fill_keys(['checks', 'clean', 'skipped', 'blocked', 'inconclusive', 'upstream_error', 'validator_error'], 0);
$modulesByClassification = array_fill_keys(['clean', 'skipped', 'blocked', 'inconclusive', 'upstream_error', 'validator_error'], []);
foreach ($records as $module => $record) {
    $classification = (string) $record['classification'];
    $summary['checks']++;
    $summary[$classification]++;
    $modulesByClassification[$classification][] = $module;
}

if (count($records) !== 1298) {
    fwrite(STDERR, 'Expected 1298 unique module results, found '.count($records).PHP_EOL);
    exit(1);
}

$output = $root.'/storage/app/september-username-validation-final.json';
$report = [
    'generated_at' => date(DATE_ATOM),
    'transport' => 'direct',
    'module_count' => count($records),
    'target_alias' => 'synthetic_availability_probe',
    'summary' => $summary,
    'modules_by_classification' => $modulesByClassification,
    'results' => array_values($records),
];
file_put_contents($output, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
fwrite(STDOUT, json_encode(['output' => $output, 'summary' => $summary], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

/** @param array<string, array<string, mixed>> $records */
function mergeReport(string $path, array &$records): void
{
    $report = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    foreach ((array) ($report['results'] ?? []) as $record) {
        if (is_array($record) && is_string($record['module'] ?? null)) {
            $records[$record['module']] = $record;
        }
    }
}
