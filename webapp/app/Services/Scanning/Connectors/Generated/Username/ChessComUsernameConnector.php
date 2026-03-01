<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class ChessComUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'chess-com';
    }

    public function category(): string
    {
        return 'gaming';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://www.chess.com/callback/user/valid?username={user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Chess Com';
    }
}
