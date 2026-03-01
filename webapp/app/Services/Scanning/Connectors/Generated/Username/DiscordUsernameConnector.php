<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class DiscordUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'discord';
    }

    public function category(): string
    {
        return 'social';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://discord.com/api/v9/unique-username/username-attempt-unauthed', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Discord';
    }
}
