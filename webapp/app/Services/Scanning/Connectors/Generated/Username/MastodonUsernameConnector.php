<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class MastodonUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'mastodon';
    }

    public function category(): string
    {
        return 'social';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://mastodon.social/api/v1/accounts/lookup?acct={user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Mastodon';
    }
}
