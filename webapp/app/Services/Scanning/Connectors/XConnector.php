<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors;

class XConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'x';
    }

    public function category(): string
    {
        return 'social';
    }

    protected function profileUrl(string $username): string
    {
        return sprintf('https://x.com/%s', rawurlencode($username));
    }

    protected function siteName(): string
    {
        return 'X';
    }
}
