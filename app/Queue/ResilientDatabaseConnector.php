<?php

declare(strict_types=1);

namespace App\Queue;

use Illuminate\Queue\Connectors\DatabaseConnector;

final class ResilientDatabaseConnector extends DatabaseConnector
{
    public function connect(array $config)
    {
        return new ResilientDatabaseQueue($this->connections->connection($config['connection'] ?? null),
            $config['table'], $config['queue'], $config['retry_after'] ?? 60, $config['after_commit'] ?? null);
    }
}
