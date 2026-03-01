<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class MonkeytypeUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'monkeytype';
    }

    public function category(): string
    {
        return 'gaming';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://api.monkeytype.com/users/checkName/{safe_user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Monkeytype';
    }
}
