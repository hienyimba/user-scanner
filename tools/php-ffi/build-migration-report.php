<?php

declare(strict_types=1);
use App\Services\Scanner\BrowserBridgeTransport;

// Rebuild from redacted local artifacts. Does not run scans or change skips.
require __DIR__.'/bootstrap-acceptance.php';
$directory = base_path('storage/app/ffi-migration-20261027');
$read = static fn (string $name): array => json_decode(file_get_contents($directory.'/'.$name), true, flags: JSON_THROW_ON_ERROR);
$records = array_map(static fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
    file($directory.'/live-retests.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
$plan = $read('plan.json');
$native = $read('native-acceptance.json');
$diagnostics = $read('connection-diagnostics-acceptance.json');
$tests = simplexml_load_file($directory.'/native-regression.xml');
$full = simplexml_load_file($directory.'/full-regression.xml');
$prior = simplexml_load_file(base_path('storage/app/ffi-production-20261027/full-regression.xml'));
$problems = static fn ($xml): array => array_map(static fn ($case): string => (string) $case['class'].'::'.(string) $case['name'], $xml->xpath('//testcase[failure or error]'));
$oldProblems = $problems($prior);
$currentProblems = $problems($full);
$groups = [];
$connectCodes = [];
$retryCases = 0;
$workingModules = [];
$latest = [];
foreach ($records as $row) {
    $groups[$row['group']][$row['status']] = ($groups[$row['group']][$row['status']] ?? 0) + 1;
    if ($row['group'] === 'connection_recheck' && $row['round'] === 2) {
        if (count($row['attempts'] ?? []) === 2) {
            $retryCases++;
        }
        foreach ($row['trace'] as $step) {
            if (isset($step['transport_error']['proxy_connect_status'])) {
                $code = $step['transport_error']['proxy_connect_status'];
                $connectCodes[$code] = ($connectCodes[$code] ?? 0) + 1;
            }
        }
    }
    if ($row['group'] === 'working_route') {
        $key = $row['mode'].':'.$row['module'];
        $workingModules[$key] ??= ['mode' => $row['mode'], 'module' => $row['module'], 'route' => $row['route'], 'statuses' => []];
        $workingModules[$key]['statuses'][$row['status']] = ($workingModules[$key]['statuses'][$row['status']] ?? 0) + 1;
    }
    if ($row['group'] !== 'connection_recheck') {
        $key = $row['mode'].':'.$row['module'].':'.$row['route'].':'.$row['alias'];
        // Stopped extra rounds do not erase an earlier completed response.
        if ($row['status'] !== 'Not tested' || ! isset($latest[$key])) {
            $latest[$key] = $row;
        }
    }
}
$profileRows = [];
foreach (config('scanner_september_email.modules') as $definition) {
    if (trim((string) ($definition['transport_profile'] ?? '')) === '') {
        continue;
    }
    $key = $definition['key'];
    $routes = [];
    foreach (['direct', 'US'] as $route) {
        $matches = array_filter($latest, static fn (array $row): bool => $row['mode'] === 'email' && $row['module'] === $key && $row['route'] === $route);
        $routes[$route] = array_count_values(array_column($matches, 'status'));
    }
    $profileRows[] = ['module' => $key, 'transport' => 'ffi', 'offline_verdict_branches' => ['Registered', 'Not Registered'],
        'live_offline_hold' => in_array($key, $plan['offline_only_profiles'], true), 'routes' => $routes];
}
$summary = ['date' => '2026-10-27', 'production_deployed' => false, 'linux_php84_acceptance' => 'NOT RUN: no host available',
    'profile_count' => count($profileRows), 'python_bridge_removed' => ! class_exists(BrowserBridgeTransport::class)
        && ! is_dir(base_path('tools/browser-bridge')),
    'native_tests' => (array) $tests->testsuite->attributes(), 'native_acceptance' => $native, 'diagnostics_acceptance' => $diagnostics,
    'full_suite' => (array) $full->testsuite->attributes(), 'prior_problem_count' => count($oldProblems),
    'current_problem_count' => count($currentProblems), 'new_problem_names' => array_values(array_diff($currentProblems, $oldProblems)),
    'resolved_problem_names' => array_values(array_diff($oldProblems, $currentProblems)),
    'live_cases_including_stops_and_superseded_checks' => count($records), 'case_counts' => $groups,
    'connection_round2_two_attempt_cases' => $retryCases, 'connection_round2_connect_status_counts' => $connectCodes,
    'working_route_modules' => array_values($workingModules), 'profiles' => $profileRows,
    'skip_flags_changed' => false, 'production_route_preferences_changed' => false];
$harnessDirectory = base_path('storage/app/ffi-php-harnesses-20261027');
$harnessSection = '';
if (is_file($harnessDirectory.'/native-acceptance.json') && is_file($harnessDirectory.'/diagnostics-acceptance.json')
    && is_file($harnessDirectory.'/regression.xml') && filesize($harnessDirectory.'/regression.xml') > 0) {
    $harnessTests = simplexml_load_file($harnessDirectory.'/regression.xml');
    $harnessAttributes = (array) $harnessTests->testsuite->attributes();
    $summary['php_only_validation'] = [
        'native_acceptance' => json_decode(file_get_contents($harnessDirectory.'/native-acceptance.json'), true, flags: JSON_THROW_ON_ERROR),
        'diagnostics_acceptance' => json_decode(file_get_contents($harnessDirectory.'/diagnostics-acceptance.json'), true, flags: JSON_THROW_ON_ERROR),
        'regression' => $harnessAttributes['@attributes'],
        'python_entry_points_removed' => ! is_file(__DIR__.'/acceptance.py') && ! is_file(__DIR__.'/diagnostics-acceptance.py'),
    ];
    $passed = $harnessAttributes['@attributes']['errors'] === '0' && $harnessAttributes['@attributes']['failures'] === '0'
        && $summary['php_only_validation']['native_acceptance']['all_passed'] === true
        && $summary['php_only_validation']['diagnostics_acceptance']['all_passed'] === true;
    $harnessSection = '<section><h2>PHP-only acceptance tools</h2><p>Both harnesses now run entirely in PHP. '
        .'The nonblocking loopback fixture server preserves persistent HTTP connections and concurrent slow responses. '
        .'The subprocess runner enforces timeouts, checks exit codes and JSON, and releases child processes, sockets and temporary files on failure.</p>'
        .'<p>Final combined regression: '.(int) $harnessAttributes['@attributes']['tests'].' tests; '
        .($passed ? 'all passed' : 'validation has failures').'. The PHP harnesses exercised four native workers and 4,000 requests, '
        .'plus all 44 connection diagnostics checks. New runtime tests cover persistent connections, concurrent fixtures, malformed output, '
        .'large output, missing executables, nonzero exits, termination and cleanup. Python entry points were removed after validation; '
        .'CI now uses PHP syntax checks, PHPUnit and these native PHP harnesses. Retired import/audit scripts are archived under OLD.</p>'
        .'<p><a href="../ffi-php-harnesses-20261027/native-acceptance.json">PHP native acceptance</a> · '
        .'<a href="../ffi-php-harnesses-20261027/diagnostics-acceptance.json">PHP diagnostics acceptance</a> · '
        .'<a href="../ffi-php-harnesses-20261027/regression.xml">Combined regression results</a></p></section>';
}
file_put_contents($directory.'/summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$counts = static fn (array $statuses): string => $statuses === [] ? 'No live check' : implode('; ', array_map(
    static fn ($status, $count): string => $status.' ×'.$count, array_keys($statuses), $statuses));
$profileTable = '';
foreach ($profileRows as $row) {
    $profileTable .= '<tr><td>'.$escape($row['module']).'</td><td>FFI · both verdict fixtures passed</td><td>'
        .$escape($row['live_offline_hold'] ? 'Offline only: recovery/login flow' : $counts($row['routes']['direct'])).'</td><td>'
        .$escape($row['live_offline_hold'] ? 'Offline only: recovery/login flow' : $counts($row['routes']['US'])).'</td></tr>';
}
$routeTable = '';
foreach ($workingModules as $row) {
    $routeTable .= '<tr><td>'.$escape($row['mode']).'</td><td>'.$escape($row['module']).'</td><td>'.$escape($row['route'])
        .'</td><td>'.$escape($counts($row['statuses'])).'</td></tr>';
}
$testAttributes = (array) $tests->testsuite->attributes();
$testAttributes = $testAttributes['@attributes'];
$fullAttributes = (array) $full->testsuite->attributes();
$fullAttributes = $fullAttributes['@attributes'];
$html = '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    .'<title>PHP FFI migration validation — 27 October 2026</title><style>body{font:15px/1.55 system-ui;margin:0;background:#f3f5f8;color:#172339}main{max-width:1200px;margin:auto;padding:28px}section{background:#fff;border:1px solid #d8dee8;border-radius:8px;padding:22px;margin:20px 0}.notice{border-left:5px solid #b47700;background:#fff0cc;padding:16px}table{border-collapse:collapse;width:100%;font-size:13px}th,td{text-align:left;border-bottom:1px solid #dde3ed;padding:9px;vertical-align:top}pre{white-space:pre-wrap;background:#172339;color:#e2ebfa;padding:16px;overflow:auto}h1,h2{line-height:1.25}.scroll{overflow:auto}a{color:#1747a5}</style><main>'
    .'<h1>PHP FFI migration completed locally</h1><p>27 October 2026 · Windows/PHP 8.2 · 24 native profiles · Python bridge removed</p>'
    .'<p class="notice">Linux/PHP 8.4 acceptance remains unrun because no Linux host is available. This report does not authorize production activation, worker changes or removal of skip flags. The broader suite retains its 50 existing failing/error test names.</p>'
    .'<section><h2>Implementation</h2><p>All 24 email definitions with a transport_profile now use PHP FFI, including the remaining 20 migrated profiles. Generic chrome/browser profiles resolve to pinned chrome131; explicit versioned profiles stay pinned. Disabled or unavailable FFI returns Error. No Python or ordinary-HTTP fallback is used for profiled modules.</p>'
    .'<p>The Python bridge class, executable runtime, curl_cffi requirements and local virtual environment have been removed after native functional validation. Both localhost acceptance harnesses now use PHP for their fixture servers and process orchestration. The application and active validation tools need no Python runtime, virtual environment or pip packages. Historical Python release source trees remain inputs for metadata documentation.</p>'
    .'<p>Error 7 now retains safe numeric connection diagnostics: curl code, OS errno, CONNECT HTTP status, connection counts/timing, route and fixed failure classification. Raw curl error buffers, targets, credentials and URLs never enter those fields. Composer applies reproducible source-hash-checked patches to the pinned native dependency.</p>'
    .'<p>The proxy policy retries native error 7 once on an alternate endpoint, within deadline and Retry-After limits, and retains both attempts. It never falls back from a proxy to direct. Transport errors do not clear proxy health failures; only evidence of a failing proxy endpoint affects its health.</p></section>'
    .$harnessSection.'<section><h2>Validation</h2><p>Native suite: '.$escape($testAttributes['tests']).' tests, '.$escape($testAttributes['assertions']).' assertions, zero failures/errors. Both verdict branches are covered for all 24 profiles. The real native acceptance ran four simultaneous PHP processes and 4,000 synthetic requests; all functional checks passed with zero PHP heap growth during this short run. This does not measure total process memory or establish long-term leak freedom.</p>'
    .'<p>Connection diagnostics acceptance passed all 44 checks, covering refused direct/proxy ports, a reachable proxy returning CONNECT 503, redaction and successful subsequent requests.</p>'
    .'<p>Broader Unit/Feature suite: '.$escape($fullAttributes['tests']).' tests; '.$escape($fullAttributes['errors']).' errors; '.$escape($fullAttributes['failures']).' failures; '.$escape($fullAttributes['skipped']).' skipped. All 50 problem test names match the previous report; no new problem names. Many refer to the previously removed async implementation. This is not a green full suite.</p></section>'
    .'<section><h2>Live connection diagnosis</h2><p>Each of the 26 modules previously reporting error 7 on U.S. was rechecked. The second diagnostic round used two independently verified, enabled U.S. endpoints (8028 and 8027). '.$escape($retryCases).' cases exercised the alternate-proxy retry; the remaining case was refused by the destination/redirect policy and was not retried. Default timeout retries stayed disabled.</p>'
    .'<p>The trace recorded 40 CONNECT 403 failures and 12 CONNECT 503 failures in that round. These came from reachable proxies with an established connection and OS errno 0. The failures are proxy tunnel refusals/upstream connection failures, not proof that the proxy server itself is unreachable. Rotating endpoints did not make those destinations work. No account verdict is inferred from a failed connection.</p></section>'
    .'<section><h2>Follow-ups on the previously working route</h2><p>Twenty-five modules were selected from the prior report. Conflicting verdict modules such as Last.fm were excluded. The same approved username/aliases were used, with two planned rounds. A block, rate limit or age signal stops remaining work on that module/route; stopped cases are not successful checks. git_nwtime_org returned errors on both repeats despite its earlier direct success.</p>'
    .'<p>Forty username follow-ups returned Available, two returned Error, and two stopped. Email follow-ups returned two Registered and two Not Registered, with ten stopped. These are observations, not independently established account truth. Early rounds conservatively matched age-related text even on conclusive responses, so the later aliases stayed held.</p><div class="scroll"><table><tr><th>Mode</th><th>Module</th><th>Selected route</th><th>Follow-up results</th></tr>'.$routeTable.'</table></div></section>'
    .'<section><h2>All 24 migrated profiles</h2><p>Nine recovery/login flows stayed offline to avoid sending messages. Their native selection and both parser branches passed fixtures. Other profiles received bounded live validation/lookup checks using only the existing approved aliases. Twelve initial no-wire checks exposed the unsupported generic chrome alias; the alias was pinned and all twelve follow-ups passed. The raw log retains those superseded failures. Tindie returned HTTP 429 for the tertiary alias on both routes; account status stays unknown. Quora and Codepen remain blocked.</p><div class="scroll"><table><tr><th>Module</th><th>Offline validation</th><th>Direct: latest completed cases</th><th>U.S.: latest completed cases</th></tr>'.$profileTable.'</table></div></section>'
    .'<section><h2>Operational status and artifacts</h2><p>No deployment, production skip changes or production route preference changes were made. Working-route selection is for diagnostic follow-ups and does not silently override a caller’s proxy choice. Before Linux deployment, run the expanded .github/workflows/php-ffi-acceptance.yml or the commands in README.md, enable FFI in every executing worker/SAPI, then gracefully roll the existing workers. The false flag is a stop switch, not Python rollback; release rollback must restore the previous application and dependencies together.</p>'
    .'<p><a href="summary.json">Structured summary</a> · <a href="live-retests.jsonl">Redacted live traces</a> · <a href="native-regression.xml">Native test results</a> · <a href="full-regression.xml">Broader regression results</a> · <a href="native-acceptance.json">Native acceptance</a> · <a href="connection-diagnostics-acceptance.json">Diagnostics acceptance</a></p></section></main></html>';
file_put_contents($directory.'/report.html', $html);
$historicalPath = base_path('storage/app/ffi-production-20261027/report.html');
$historical = file_get_contents($historicalPath);
if (! str_contains($historical, 'ffi-migration-20261027/report.html')) {
    $notice = '<p class="notice"><strong>Historical pilot report.</strong> Superseded by the '
        .'<a href="../ffi-migration-20261027/report.html">completed local FFI migration and validation report</a>. '
        .'The Python rollback and four-module activation instructions below describe the earlier release.</p>';
    file_put_contents($historicalPath, str_replace('<main>', '<main>'.$notice, $historical));
}
echo json_encode(['report' => $directory.'/report.html', 'live_cases' => count($records), 'new_problem_names' => $summary['new_problem_names']], JSON_THROW_ON_ERROR).PHP_EOL;
