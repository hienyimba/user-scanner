<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class RedditUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'reddit';
    }

    public function category(): string
    {
        return 'social';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://www.reddit.com/user/{user}/', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Reddit';
    }
}
