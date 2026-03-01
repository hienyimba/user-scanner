<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class MinecraftUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'minecraft';
    }

    public function category(): string
    {
        return 'gaming';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://api.mojang.com/minecraft/profile/lookup/name/{user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Minecraft';
    }
}
