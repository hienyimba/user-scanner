<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class LemmyUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'lemmy';
    }

    public function category(): string
    {
        return 'community';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://lemmy.world/api/v3/user?username={user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Lemmy';
    }
}
