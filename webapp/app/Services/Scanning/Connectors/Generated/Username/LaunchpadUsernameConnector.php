<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class LaunchpadUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'launchpad';
    }

    public function category(): string
    {
        return 'dev';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://launchpad.net/~{user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Launchpad';
    }
}
