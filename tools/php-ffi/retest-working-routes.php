<?php

declare(strict_types=1);

use App\DTO\ScanResult;
use App\Services\Scanner\BrowserTransportSession;
use App\Services\Scanner\NativeTransportException;
use App\Services\Scanner\PhpFfiTransport;
use App\Services\Scanner\ProxyExecutionPolicy;
use App\Services\Scanner\ProxyManagerService;
use App\Services\Scanner\ScannerDiagnostics;
use App\Services\Scanner\WorkingRouteRetestPlan;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Promise\Create;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

require __DIR__.'/bootstrap-acceptance.php';
$arguments = getopt('', ['execute', 'rounds:', 'connections-only', 'connection-round:', 'modules:', 'profile-round:']);
$rounds = max(1, min(3, (int) ($arguments['rounds'] ?? 2)));
$directory = base_path('storage/app/ffi-migration-20261027');
if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}
$old = array_map(static fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
    file(base_path('storage/app/ffi-production-20261027/live-skips.jsonl'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
$working = WorkingRouteRetestPlan::build($old);
$profiles = array_values(array_filter(config('scanner_september_email.modules'),
    static fn (array $definition): bool => trim((string) ($definition['transport_profile'] ?? '')) !== ''));
// These requests send recovery/login messages. Their native transport and both
// verdict branches are validated with offline fixtures, never live recipients.
$offline = ['cv_ee', 'cv_lv', 'cvonline_lt', 'pulser', 'cvkeskus', 'cvmarket_lt', 'cvmarket_lv', 'hercul', 'jobs_cz'];
$emailAliases = ['baseline_email_primary', 'baseline_email_secondary', 'baseline_email_tertiary'];
$jobs = [];
$connectionModules = [];
foreach ($old as $row) {
    $key = $row['mode'].':'.$row['module'];
    if ($row['route'] === 'US' && str_contains($row['reason'] ?? '', 'failed (7)') && ! isset($connectionModules[$key])) {
        $connectionModules[$key] = true;
        $jobs[] = ['group' => 'connection_recheck', 'round' => max(1, (int) ($arguments['connection-round'] ?? 1)), 'mode' => $row['mode'],
            'module' => $row['module'], 'route' => 'US', 'alias' => $row['alias']];
    }
}
foreach ($working as $row) {
    for ($round = 1; $round <= $rounds; $round++) {
        foreach ($row['aliases'] as $alias) {
            $jobs[] = ['group' => 'working_route', 'round' => $round, 'mode' => $row['mode'],
                'module' => $row['module'], 'route' => $row['route'], 'alias' => $alias];
        }
    }
}
foreach ($profiles as $definition) {
    if (in_array($definition['key'], $offline, true)
        || array_filter($working, static fn (array $row): bool => $row['mode'] === 'email' && $row['module'] === $definition['key'])) {
        continue;
    }
    foreach (['direct', 'US'] as $route) {
        foreach ($emailAliases as $alias) {
            $jobs[] = ['group' => 'profile_migration', 'round' => max(1, (int) ($arguments['profile-round'] ?? 1)), 'mode' => 'email',
                'module' => $definition['key'], 'route' => $route, 'alias' => $alias];
        }
    }
}
$plan = ['production_mutations' => false, 'working_routes' => $working, 'profile_count' => count($profiles),
    'offline_only_profiles' => $offline, 'jobs' => $jobs, 'rounds' => $rounds];
if (array_key_exists('connections-only', $arguments)) {
    $jobs = array_values(array_filter($jobs, static fn (array $row): bool => $row['group'] === 'connection_recheck'));
    $plan['jobs'] = $jobs;
}
if (isset($arguments['modules'])) {
    $selectedModules = explode(',', (string) $arguments['modules']);
    $jobs = array_values(array_filter($jobs, static fn (array $row): bool => in_array($row['module'], $selectedModules, true)));
    $plan['jobs'] = $jobs;
}
$planName = array_key_exists('connections-only', $arguments) ? '/connection-plan.json'
    : (isset($arguments['modules']) ? '/module-followup-plan.json' : '/plan.json');
file_put_contents($directory.$planName, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
if (! array_key_exists('execute', $arguments)) {
    echo json_encode(['planned_cases' => count($jobs), 'working_route_modules' => count($working),
        'offline_only_profiles' => count($offline), 'network_requests' => 0]).PHP_EOL;
    exit(0);
}
config(['scanner.php_ffi.enabled' => true, 'scanner.metadata.fetch_profile_html' => false,
    'scanner.php_ffi.ca_bundle' => base_path('storage/app/email-skips-20261019-22/upstream-ca.pem'),
    'scanner.proxies.behavior.wait_timeout_seconds' => 0]);
$native = new PhpFfiTransport;
if (! $native->isAvailable()) {
    throw new RuntimeException('Pinned native FFI preflight failed');
}
$manager = app(ProxyManagerService::class);
$verified = [];
$candidates = array_slice(array_values(array_filter(config('scanner.proxies.pool'),
    static fn (array $row): bool => ($row['enabled'] ?? true) && ($row['country_code'] ?? '') === 'US')), 0, 2);
foreach ($candidates as $candidate) {
    $port = (int) $candidate['port'];
    $raw = $candidate['entry_point'].':'.$port;
    try {
        $proxy = $manager->resolve($raw);
        $location = $native->send(['url' => 'https://ip.oxylabs.io/location', 'method' => 'GET',
            'proxy' => $proxy, 'timeout' => 8, 'follow_redirects' => false], new CookieJar)->json();
        $countries = [];
        if (is_array($location)) {
            array_walk_recursive($location, static function ($value, $key) use (&$countries): void {
                if (is_string($value) && in_array(strtolower((string) $key), ['country', 'country_code', 'countrycode', 'code'], true)) {
                    $countries[] = strtoupper($value);
                }
            });
        }
        if (in_array('US', $countries, true) || in_array('UNITED STATES', $countries, true)) {
            $verified[$raw] = $proxy;
        }
    } catch (Throwable $error) {
        echo json_encode(['preflight_port' => $port, 'status' => 'Error',
            'transport_error' => $error instanceof NativeTransportException ? $error->diagnostics : []]).PHP_EOL;
    }
}
if ($verified === []) {
    throw new RuntimeException('No verified U.S. route; no target requests started');
}
file_put_contents($directory.'/route-preflight.json', json_encode(['verified_us_endpoints' => array_keys($verified)], JSON_THROW_ON_ERROR));
$manager->loadFromText(implode(PHP_EOL, array_keys($verified)));
$targets = ['approved_username' => 'hienyimba'];
foreach ($emailAliases as $alias) {
    $email = config('scanner_private_targets.email.'.$alias);
    if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Approved baseline alias missing');
    }
    $targets[$alias] = $email;
}
$registry = [];
foreach (app('scanner.validators') as $validator) {
    $registry[$validator->mode().':'.$validator->key()] = $validator;
}
$state = null;
$guarded = new class($native, $state) extends PhpFfiTransport
{
    public function __construct(private PhpFfiTransport $native, public mixed &$state) {}

    public function send(array $spec, CookieJar $jar): Response
    {
        if ($this->state === null) {
            throw new RuntimeException('Unscoped diagnostic request');
        }
        if ($this->state['blocked']) {
            throw new RuntimeException('Stopped after upstream block/rate/age gate');
        }
        $spec['deadline_ns'] = min((int) ($spec['deadline_ns'] ?? PHP_INT_MAX), $this->state['deadline']);
        $spec['redact_values'] = [$this->state['target']];
        $spec['allowed_redirect_hosts'] = $this->state['hosts'];

        return app(BrowserTransportSession::class)->send($spec, $jar, function (array $hop) {
            if ($this->state['blocked']) {
                throw new RuntimeException('Stopped after upstream block/rate/age gate');
            }
            $host = strtolower((string) parse_url($hop['url'], PHP_URL_HOST));
            if (! in_array($host, $this->state['hosts'], true) || ($hop['proxy'] ?? null) !== $this->state['proxy']
                || ! in_array($hop['method'], ['GET', 'HEAD', 'POST'], true) || ++$this->state['requests'] > 16) {
                throw new RuntimeException('Diagnostic destination/method/route/request guard refused request');
            }
            $wire = rawurldecode($hop['url'].' '.(is_string($hop['body'] ?? null) ? $hop['body'] : json_encode($hop['body'] ?? [])));
            if ($this->state['mode'] === 'email') {
                preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $wire, $emails);
                foreach ($emails[0] as $email) {
                    if (strcasecmp($email, $this->state['target']) !== 0) {
                        throw new RuntimeException('Diagnostic additional-target guard refused request');
                    }
                }
            }
            $start = microtime(true);
            try {
                $response = $this->native->singleHop($hop);
            } catch (NativeTransportException $error) {
                $this->state['trace'][] = ['method' => $hop['method'], 'host' => $host,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2), 'transport_error' => $error->diagnostics];
                throw $error;
            }
            $this->state['trace'][] = ['method' => $hop['method'], 'host' => $host, 'http_status' => $response->status(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2), 'body_bytes' => strlen($response->body()),
                'body_sha256' => hash('sha256', $response->body())];
            if (in_array($response->status(), [403, 429], true)
                || preg_match('/age.verification|verify your age|age restricted|<title>\s*(?:Just a moment|Client Challenge|Attention Required|Access Denied)/i', $response->body())) {
                $this->state['blocked'] = true;
            }

            return $response;
        });
    }
};
app()->instance(PhpFfiTransport::class, $guarded);
// Username/non-profile diagnostic requests also use the native engine. Their
// production transports are unchanged; only the 24 profiles are migrated.
Http::globalMiddleware(static function ($handler) use ($guarded, &$state) {
    return static function ($request, $options) use ($guarded, &$state) {
        if ($state === null) {
            throw new RuntimeException('Unscoped HTTP request refused');
        }
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            if (strcasecmp($name, 'Cookie') === 0 && ($options['cookies'] ?? null) instanceof CookieJar) {
                continue;
            }
            $headers[$name] = implode(', ', $values);
        }
        $jar = ($options['cookies'] ?? null) instanceof CookieJar ? $options['cookies'] : new CookieJar;
        $response = $guarded->send(['url' => (string) $request->getUri(), 'method' => $request->getMethod(),
            'headers' => $headers, 'body_mode' => 'raw', 'body' => (string) $request->getBody(), 'proxy' => $state['proxy'],
            'timeout' => min(15, (float) ($options['timeout'] ?? 15)), 'follow_redirects' => (bool) ($options['allow_redirects'] ?? true)], $jar);

        return Create::promiseFor($response->toPsrResponse());
    };
});
$output = $directory.'/live-retests.jsonl';
$done = [];
$blocked = [];
$identity = static fn (array $row): string => implode(':', array_intersect_key($row, array_flip(['group', 'round', 'mode', 'module', 'route', 'alias'])));
if (is_file($output)) {
    foreach (file($output, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        $done[$identity($record)] = true;
        if ($record['blocked'] ?? false) {
            $blocked[$record['mode'].':'.$record['module'].':'.$record['route']] = true;
        }
    }
}
foreach ($jobs as $job) {
    if (isset($done[$identity($job)])) {
        continue;
    }
    $key = $job['mode'].':'.$job['module'];
    $stopKey = $key.':'.$job['route'];
    $validator = $registry[$key] ?? null;
    $record = $job + ['transport' => 'ffi', 'production_transport' => $job['mode'] === 'email' && in_array($job['module'], config('scanner.php_ffi.email_modules'), true)];
    if (isset($blocked[$stopKey]) || $validator === null) {
        $record += ['status' => 'Not tested', 'reason' => $validator === null ? 'Registry module missing' : 'Stopped after upstream block/rate/age gate', 'trace' => []];
    } else {
        $target = $targets[$job['alias']] ?? throw new RuntimeException('Unapproved alias in plan');
        $reflection = new ReflectionObject($validator);
        $source = file_get_contents($reflection->getFileName());
        $urls = [$validator->siteUrl()];
        preg_match_all('~https://[a-zA-Z0-9._{}-]+~', $source, $matches);
        $urls = [...$urls, ...$matches[0]];
        if ($reflection->hasProperty('definition')) {
            $definition = $reflection->getProperty('definition')->getValue($validator);
            array_walk_recursive($definition, static function ($value) use (&$urls): void {
                if (is_string($value) && str_starts_with($value, 'https://')) {
                    $urls[] = $value;
                }
            });
        }
        $hosts = [];
        foreach ($urls as $url) {
            $host = strtolower((string) parse_url(str_replace(['{target}', '{user}', '{username}'], $target, $url), PHP_URL_HOST));
            if ($host !== '' && ! str_contains($host, '{')) {
                $hosts[] = $host;
                $hosts[] = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.'.$host;
            }
        }
        $state = ['mode' => $job['mode'], 'target' => $target, 'proxy' => null, 'hosts' => array_unique($hosts),
            'deadline' => hrtime(true) + 35_000_000_000, 'trace' => [], 'requests' => 0, 'blocked' => false];
        config(['scanner.proxies.behavior.max_retry_per_module' => 1]);
        $attempt = static function (?string $proxy) use ($validator, $target, &$state): ScanResult {
            $state['proxy'] = $proxy;
            $state['requests'] = 0;
            $result = $validator->check($target, ['proxy' => $proxy, 'fetch_metadata' => false, 'fetch_profile_html' => false,
                'allow_loud' => false, '_scanner_deadline_ns' => $state['deadline']]);
            if ($result->status === 'Error' && preg_match('/blocked|rate[\s-]limit|anti.bot|captcha|challenge|age.verification/i', $result->reason)) {
                $state['blocked'] = true;
            }
            // Older username validators preserve only the exception message.
            // Recover our guarded native trace's numeric evidence for the
            // proxy retry policy without exposing request bodies or targets.
            $last = $state['trace'] === [] ? [] : $state['trace'][array_key_last($state['trace'])];
            if ($result->status === 'Error' && ! isset($result->metadata['transport_error']) && isset($last['transport_error'])) {
                $result = ScanResult::fromArray([...$result->toArray(),
                    'metadata' => array_merge($result->metadata, ['transport_error' => $last['transport_error']])]);
            }
            // Live review rotates only transport failures. An observed service
            // block ends this route without another request on a fresh exit.
            if ($state['blocked']) {
                config(['scanner.proxies.behavior.max_retry_per_module' => 0]);
            }

            return $result;
        };
        $start = microtime(true);
        try {
            $result = $job['route'] === 'direct' ? $attempt(null)
                : ProxyExecutionPolicy::run($manager, ['retry_country' => 'US', '_scanner_deadline_ns' => $state['deadline']], 0,
                    $attempt, static fn (string $reason): ScanResult => ScanResult::fromArray(['status' => 'Error', 'reason' => $reason]),
                    context: ['mode' => $job['mode'], 'module' => $job['module'], 'redact_values' => [$target]]);
            $record += ['status' => $result->status, 'reason' => ScannerDiagnostics::redact($result->reason, [$target]),
                'attempts' => $result->metadata['attempts'] ?? [], 'transport_error' => $result->metadata['transport_error'] ?? []];
        } catch (Throwable $error) {
            $record += ['status' => 'Error', 'reason' => ScannerDiagnostics::redact($error->getMessage(), [$target]),
                'transport_error' => $error instanceof NativeTransportException ? $error->diagnostics : []];
        }
        $record += ['duration_ms' => round((microtime(true) - $start) * 1000, 2), 'trace' => $state['trace'], 'blocked' => $state['blocked']];
        if ($state['blocked']) {
            $blocked[$stopKey] = true;
        }
    }
    file_put_contents($output, json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND | LOCK_EX);
    echo json_encode($job + ['status' => $record['status'], 'steps' => count($record['trace'])], JSON_THROW_ON_ERROR).PHP_EOL;
}
