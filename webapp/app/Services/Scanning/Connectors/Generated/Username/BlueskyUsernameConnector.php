<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class BlueskyUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'bluesky';
    }

    public function category(): string
    {
        return 'social';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://bsky.social/xrpc/com.atproto.temp.checkHandleAvailability', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Bluesky';
    }
}
