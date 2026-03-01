<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class BuymeacoffeeUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'buymeacoffee';
    }

    public function category(): string
    {
        return 'donation';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://buymeacoffee.com/{user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Buymeacoffee';
    }
}
