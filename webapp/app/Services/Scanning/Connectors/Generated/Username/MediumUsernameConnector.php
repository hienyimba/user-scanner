<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class MediumUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'medium';
    }

    public function category(): string
    {
        return 'creator';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://medium.com/@{user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Medium';
    }
}
