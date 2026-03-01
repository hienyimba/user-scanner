<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class TelegramUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'telegram';
    }

    public function category(): string
    {
        return 'social';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://t.me/{user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Telegram';
    }
}
