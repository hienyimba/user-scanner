<?php

declare(strict_types=1);

use App\DTO\ScanResult;
use App\Services\Scanner\ProxyExecutionPolicy;
use App\Services\Scanner\ProxyManagerService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
foreach (['APP_CONFIG_CACHE' => __DIR__.'/absent-config.php', 'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $argv[1], 'DB_URL' => '', 'CACHE_STORE' => 'database', 'LOG_CHANNEL' => 'null',
    'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $argv[1],
    'database.connections.sqlite.url' => null, 'cache.default' => 'database', 'cache.prefix' => 'module-cooldown-test:',
    'scanner.proxies.pool' => [], 'scanner.proxies.behavior.wait_timeout_seconds' => 0,
    'scanner.proxies.behavior.max_retry_per_module' => 0, 'scanner.proxies.behavior.max_concurrent_per_proxy' => 1]);
$manager = new ProxyManagerService;
$manager->loadFromText("http://first.invalid:8001\nhttp://second.invalid:8002");
$action = $argv[2];
$scope = ['mode' => 'email', 'module' => $action === 'other-success' ? 'beta' : 'alpha'];
$seen = null;
ProxyExecutionPolicy::run($manager, [], 0, function ($proxy) use (&$seen, $action): ScanResult {
    $seen = $proxy;

    return ScanResult::fromArray(['status' => $action === 'write' ? 'Error' : 'Registered',
        'metadata' => $action === 'write' ? ['http_status' => 429, 'retry_after_seconds' => 86400] : ['http_status' => 200]]);
}, static fn ($reason): ScanResult => ScanResult::fromArray(['status' => 'Error', 'reason' => $reason]), context: $scope);
echo json_encode(['pid' => getmypid(), 'proxy' => $seen,
    'remaining' => $manager->moduleCooldownRemaining('http://first.invalid:8001', ['mode' => 'email', 'module' => 'alpha'])], JSON_THROW_ON_ERROR);
