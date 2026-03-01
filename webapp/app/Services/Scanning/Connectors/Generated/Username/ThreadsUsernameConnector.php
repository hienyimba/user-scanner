<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class ThreadsUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'threads';
    }

    public function category(): string
    {
        return 'social';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://www.threads.net/api/v1/users/web_profile_info/?username={user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Threads';
    }
}
