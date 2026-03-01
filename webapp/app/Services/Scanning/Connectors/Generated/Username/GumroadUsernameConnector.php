<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class GumroadUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'gumroad';
    }

    public function category(): string
    {
        return 'creator';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://{user}.gumroad.com/', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Gumroad';
    }
}
