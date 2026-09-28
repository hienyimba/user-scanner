<?php

declare(strict_types=1);

use App\Services\Scanner\ScannerEngineService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$arguments = getopt('', ['use-proxy', 'diagnostics', 'modules::', 'aliases::', 'output::', 'shard::', 'plan']);
$useProxy = array_key_exists('use-proxy', $arguments);
$moduleFilter = csvOption($arguments['modules'] ?? null);
$aliasFilter = csvOption($arguments['aliases'] ?? null);
$output = is_string($arguments['output'] ?? null) && trim($arguments['output']) !== ''
    ? trim($arguments['output'])
    : storage_path('app/september-email-validation-'.($useProxy ? 'proxy' : 'direct').'.json');

$definitions = (array) config('scanner_september_email.modules', []);
$moduleKeys = array_values(array_unique(array_merge(
    array_values(array_filter(array_column($definitions, 'key'), 'is_string')),
    ['dropbox', 'gravatar'],
)));
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

$aliases = array_filter(
    (array) config('scanner_private_targets.email', []),
    static fn (mixed $target): bool => is_string($target) && filter_var($target, FILTER_VALIDATE_EMAIL) !== false,
);
if ($aliasFilter !== []) {
    $aliases = array_intersect_key($aliases, array_fill_keys($aliasFilter, true));
}

if ($moduleKeys === [] || $aliases === []) {
    fwrite(STDERR, "No modules or configured email aliases were selected.\n");
    exit(2);
}

/** @var ScannerEngineService $engine */
$engine = $app->make(ScannerEngineService::class);
if (array_key_exists('plan', $arguments)) {
    $registered = [];
    foreach ($engine->listModules('email') as $module) {
        $registered[(string) $module['key']] = $module;
    }
    $loud = [];
    $skipActive = [];
    $missing = [];
    foreach ($moduleKeys as $moduleKey) {
        if (! isset($registered[$moduleKey])) {
            $missing[] = $moduleKey;

            continue;
        }
        if ((bool) ($registered[$moduleKey]['loud'] ?? false)) {
            $loud[] = $moduleKey;
        }
        if ((bool) ($registered[$moduleKey]['skip_active'] ?? false)) {
            $skipActive[] = $moduleKey;
        }
    }
    fwrite(STDOUT, json_encode([
        'module_count' => count($moduleKeys),
        'target_aliases' => array_keys($aliases),
        'planned_checks' => count($moduleKeys) * count($aliases),
        'registered_modules' => count($moduleKeys) - count($missing),
        'missing_modules' => $missing,
        'loud_modules' => $loud,
        'loud_module_count' => count($loud),
        'active_skip_modules' => $skipActive,
        'active_skip_module_count' => count($skipActive),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit($missing === [] ? 0 : 1);
}

$records = [];
$summary = [
    'checks' => 0,
    'clean' => 0,
    'skipped' => 0,
    'blocked' => 0,
    'inconclusive' => 0,
    'upstream_error' => 0,
    'validator_error' => 0,
];

foreach ($moduleKeys as $moduleKey) {
    $moduleCounts = array_fill_keys(['clean', 'skipped', 'blocked', 'inconclusive', 'upstream_error', 'validator_error'], 0);
    foreach ($aliases as $alias => $target) {
        $result = $engine->runPlannedValidator('email', $moduleKey, $target, [
            'allow_loud' => false,
            'diagnostic_response' => array_key_exists('diagnostics', $arguments),
            'enrich_metadata' => false,
            'use_proxy' => $useProxy,
        ]);
        $reason = redactTarget($result->reason, $target);
        $httpStatus = isset($result->metadata['http_status']) ? (int) $result->metadata['http_status'] : null;
        $classification = classifyResult($result->status, $reason, $result->metadata['status_detail'] ?? null, $httpStatus);
        $record = [
            'module' => $moduleKey,
            'target_alias' => $alias,
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
        $records[] = $record;
        $summary['checks']++;
        $summary[$classification]++;
        $moduleCounts[$classification]++;
    }

    fwrite(STDOUT, json_encode(['module' => $moduleKey, 'counts' => $moduleCounts], JSON_UNESCAPED_SLASHES).PHP_EOL);
}

$modulesByClassification = [];
foreach (array_keys($moduleCounts) as $classification) {
    $modulesByClassification[$classification] = array_values(array_unique(array_column(
        array_filter($records, static fn (array $record): bool => $record['classification'] === $classification),
        'module',
    )));
    sort($modulesByClassification[$classification]);
}

$report = [
    'generated_at' => now()->toIso8601String(),
    'transport' => $useProxy ? 'configured_proxy_pool' : 'direct',
    'module_count' => count($moduleKeys),
    'target_aliases' => array_keys($aliases),
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
    if (in_array($status, ['Registered', 'Not Registered'], true)) {
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
    if (preg_match('/blocked|rate[ _-]?limit|captcha|cloudflare|forbidden|http 403|http 429/i', $evidence) === 1) {
        return 'blocked';
    }
    if (preg_match('/curl error|timed? out|timeout|could not resolve|failed to connect|network|ssl|tls|connection reset/i', $evidence) === 1) {
        return 'inconclusive';
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
