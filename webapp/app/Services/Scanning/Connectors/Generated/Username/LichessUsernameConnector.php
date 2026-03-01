<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class LichessUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'lichess';
    }

    public function category(): string
    {
        return 'gaming';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://lichess.org/api/player/autocomplete?term={user}&exists=1', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Lichess';
    }
}
