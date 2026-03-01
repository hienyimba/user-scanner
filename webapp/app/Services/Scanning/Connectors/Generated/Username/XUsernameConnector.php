<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class XUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'x';
    }

    public function category(): string
    {
        return 'social';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://api.twitter.com/i/users/username_available.json', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'X';
    }
}
