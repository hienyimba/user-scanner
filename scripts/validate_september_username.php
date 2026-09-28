<?php

declare(strict_types=1);

use App\Services\Scanner\ScannerEngineService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$arguments = getopt('', ['use-proxy', 'diagnostics', 'modules::', 'output::', 'shard::', 'target::', 'plan', 'quiet']);
$useProxy = array_key_exists('use-proxy', $arguments);
$moduleFilter = csvOption($arguments['modules'] ?? null);
$target = is_string($arguments['target'] ?? null) && trim($arguments['target']) !== ''
    ? trim($arguments['target'])
    : 'codexseptprobe92837';
$output = is_string($arguments['output'] ?? null) && trim($arguments['output']) !== ''
    ? trim($arguments['output'])
    : storage_path('app/september-username-validation-'.($useProxy ? 'proxy' : 'direct').'.json');

$moduleKeys = array_values(array_filter(
    array_column((array) config('scanner_september_username.modules', []), 'key'),
    'is_string',
));
if ($moduleFilter !== []) {
    $moduleKeys = array_values(array_intersect($moduleKeys, $moduleFilter));
}
sort($moduleKeys);
if (is_string($arguments['shard'] ?? null) && preg_match('/^(\d+)\/(\d+)$/', $arguments['shard'], $shard) === 1) {
    $shardIndex = (int) $shard[1];
    $shardCount = (int) $shard[2];
    if ($shardIndex < 1 || $shardCount < 1 || $shardIndex > $shardCount) {
        fwrite(STDERR, "Invalid shard; expected an index such as 1/4.\n");
        exit(2);
    }
    $moduleKeys = array_values(array_filter(
        $moduleKeys,
        static fn (string $key, int $index): bool => $index % $shardCount === $shardIndex - 1,
        ARRAY_FILTER_USE_BOTH,
    ));
}

if ($moduleKeys === []) {
    fwrite(STDERR, "No September username modules were selected.\n");
    exit(2);
}

/** @var ScannerEngineService $engine */
$engine = $app->make(ScannerEngineService::class);
$registered = [];
foreach ($engine->listModules('username') as $module) {
    $registered[(string) $module['key']] = $module;
}
$missing = array_values(array_filter($moduleKeys, static fn (string $key): bool => ! isset($registered[$key])));

if (array_key_exists('plan', $arguments)) {
    fwrite(STDOUT, json_encode([
        'module_count' => count($moduleKeys),
        'planned_checks' => count($moduleKeys),
        'target_alias' => 'synthetic_availability_probe',
        'registered_modules' => count($moduleKeys) - count($missing),
        'missing_modules' => $missing,
        'transport' => $useProxy ? 'configured_proxy_pool' : 'direct',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit($missing === [] ? 0 : 1);
}

$records = [];
$summary = array_fill_keys(['checks', 'clean', 'skipped', 'blocked', 'inconclusive', 'upstream_error', 'validator_error'], 0);
foreach ($moduleKeys as $moduleKey) {
    $result = $engine->runPlannedValidator('username', $moduleKey, $target, [
        'allow_loud' => false,
        'diagnostic_response' => array_key_exists('diagnostics', $arguments),
        'enrich_metadata' => false,
        'use_proxy' => $useProxy,
    ]);
    $reason = redactTarget($result->reason, $target);
    $httpStatus = isset($result->metadata['http_status']) ? (int) $result->metadata['http_status'] : null;
    $classification = classifyResult($result->status, $reason, $result->metadata['status_detail'] ?? null, $httpStatus);
    $summary['checks']++;
    $summary[$classification]++;
    $records[] = [
        'module' => $moduleKey,
        'status' => $result->status,
        'classification' => $classification,
        'status_detail' => $result->metadata['status_detail'] ?? null,
        'http_status' => $result->metadata['http_status'] ?? null,
        'latency_ms' => $result->metadata['latency_ms'] ?? null,
        'reason' => $reason,
        'diagnostic_content_type' => $result->metadata['diagnostic_content_type'] ?? null,
        'diagnostic_body_sha256' => $result->metadata['diagnostic_body_sha256'] ?? null,
        'diagnostic_body_excerpt' => isset($result->metadata['diagnostic_body_excerpt'])
            ? redactTarget((string) $result->metadata['diagnostic_body_excerpt'], $target)
            : null,
    ];
    if (! array_key_exists('quiet', $arguments)) {
        fwrite(STDOUT, json_encode([
            'module' => $moduleKey,
            'status' => $result->status,
            'classification' => $classification,
            'http_status' => $httpStatus,
        ], JSON_UNESCAPED_SLASHES).PHP_EOL);
    }
}

$modulesByClassification = [];
foreach (array_keys($summary) as $classification) {
    if ($classification === 'checks') {
        continue;
    }
    $modulesByClassification[$classification] = array_values(array_column(
        array_filter($records, static fn (array $record): bool => $record['classification'] === $classification),
        'module',
    ));
}

$report = [
    'generated_at' => now()->toIso8601String(),
    'transport' => $useProxy ? 'configured_proxy_pool' : 'direct',
    'module_count' => count($moduleKeys),
    'target_alias' => 'synthetic_availability_probe',
    'summary' => $summary,
    'modules_by_classification' => $modulesByClassification,
    'results' => $records,
];

$directory = dirname($output);
if (! is_dir($directory)) {
    mkdir($directory, 0775, true);
}
file_put_contents($output, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
fwrite(STDOUT, json_encode(['complete' => true, 'output' => $output, 'summary' => $summary], JSON_UNESCAPED_SLASHES).PHP_EOL);

/** @return array<int, string> */
function csvOption(mixed $value): array
{
    if (! is_string($value) || trim($value) === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $value))));
}

function classifyResult(string $status, string $reason, mixed $statusDetail, ?int $httpStatus): string
{
    // Direct validators expose Taken/Available; ScannerEngineService normalizes
    // username verdicts to Found/Not Found for the application boundary.
    if (in_array($status, ['Taken', 'Available', 'Found', 'Not Found'], true)) {
        return 'clean';
    }
    if ($status === 'Skipped') {
        return 'skipped';
    }
    if ($httpStatus !== null && $httpStatus >= 500) {
        return 'upstream_error';
    }

    $detail = is_scalar($statusDetail) ? (string) $statusDetail : '';
    $evidence = $detail.' '.$reason;
    if ($httpStatus === 406 || preg_match('/blocked|rate[ _-]?limit|captcha|challenge|cloudflare|forbidden|http 401|http 403|http 429|redirected profile lookup/i', $evidence) === 1) {
        return 'blocked';
    }
    if (preg_match('/curl error|timed? out|timeout|could not resolve|failed to connect|network|ssl|tls|connection reset|connection refused/i', $evidence) === 1) {
        return 'inconclusive';
    }
    if (preg_match('/(?:Discourse|Lemmy) 404 response missing expected error payload|server is currently down|service unavailable|forum closed/i', $evidence) === 1) {
        return 'upstream_error';
    }

    return 'validator_error';
}

function redactTarget(string $value, string $target): string
{
    return str_ireplace(
        array_unique([$target, rawurlencode($target), urlencode($target), json_encode($target, JSON_THROW_ON_ERROR)]),
        '[target]',
        $value,
    );
}
