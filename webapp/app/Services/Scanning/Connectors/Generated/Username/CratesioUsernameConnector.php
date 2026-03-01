<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class CratesioUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'cratesio';
    }

    public function category(): string
    {
        return 'dev';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://crates.io/api/v1/users/{user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Cratesio';
    }
}
