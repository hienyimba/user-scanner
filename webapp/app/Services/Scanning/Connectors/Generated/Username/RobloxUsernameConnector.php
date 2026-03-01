<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class RobloxUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'roblox';
    }

    public function category(): string
    {
        return 'gaming';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://users.roblox.com/v1/users/search?keyword={user}&limit=10', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Roblox';
    }
}
