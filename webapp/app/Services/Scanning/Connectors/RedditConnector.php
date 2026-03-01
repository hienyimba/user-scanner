<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors;

class RedditConnector extends BaseUsernameConnector
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
        return sprintf('https://www.reddit.com/user/%s/', rawurlencode($username));
    }

    protected function siteName(): string
    {
        return 'Reddit';
    }
}
