<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class DevtoUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'devto';
    }

    public function category(): string
    {
        return 'creator';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://dev.to/{user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Devto';
    }
}
