<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class SubstackUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'substack';
    }

    public function category(): string
    {
        return 'creator';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://{user}.substack.com', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Substack';
    }
}
