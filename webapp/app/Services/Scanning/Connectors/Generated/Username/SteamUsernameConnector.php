<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class SteamUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'steam';
    }

    public function category(): string
    {
        return 'gaming';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://steamcommunity.com/id/{user}/', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Steam';
    }
}
