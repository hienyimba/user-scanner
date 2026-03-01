<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class VintedUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'vinted';
    }

    public function category(): string
    {
        return 'shopping';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://www.vinted.pt/member/general/search?search_text={user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Vinted';
    }
}
