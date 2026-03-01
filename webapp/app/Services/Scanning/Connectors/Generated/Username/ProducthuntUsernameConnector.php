<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class ProducthuntUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'producthunt';
    }

    public function category(): string
    {
        return 'creator';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://www.producthunt.com/@{user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Producthunt';
    }
}
