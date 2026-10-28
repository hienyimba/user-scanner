<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';
foreach (['APP_CONFIG_CACHE' => __DIR__.'/absent-config-cache.php', 'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
    'LOG_CHANNEL' => 'null', 'QUEUE_CONNECTION' => 'sync', 'SCANNER_QUEUE_CONNECTION' => 'sync'] as $name => $value) {
    putenv($name.'='.$value);
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
    'cache.default' => 'array', 'logging.default' => 'null']);

return $app;
