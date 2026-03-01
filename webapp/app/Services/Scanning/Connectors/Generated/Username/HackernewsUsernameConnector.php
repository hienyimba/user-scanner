<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class HackernewsUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'hackernews';
    }

    public function category(): string
    {
        return 'community';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://news.ycombinator.com/user?id={user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Hackernews';
    }
}
