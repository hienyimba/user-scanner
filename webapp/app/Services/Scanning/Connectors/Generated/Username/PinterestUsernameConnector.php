<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class PinterestUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'pinterest';
    }

    public function category(): string
    {
        return 'social';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://www.pinterest.com/{user}/', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Pinterest';
    }
}
