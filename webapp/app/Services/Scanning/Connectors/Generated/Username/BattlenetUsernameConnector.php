<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class BattlenetUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'battlenet';
    }

    public function category(): string
    {
        return 'gaming';
    }

    protected function profileUrl(string $username): string
    {
        return sprintf('https://overwatch.blizzard.com/en-us/search/account-by-name/%s', rawurlencode($username));
    }

    protected function siteName(): string
    {
        return 'Battlenet';
    }
}
