<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class TwitchUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'twitch';
    }

    public function category(): string
    {
        return 'creator';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://gql.twitch.tv/gql', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Twitch';
    }
}
