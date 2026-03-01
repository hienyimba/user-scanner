<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class ReplitUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'replit';
    }

    public function category(): string
    {
        return 'dev';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://replit.com/@{user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Replit';
    }
}
