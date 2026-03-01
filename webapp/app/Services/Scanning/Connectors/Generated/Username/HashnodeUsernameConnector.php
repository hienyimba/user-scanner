<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class HashnodeUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'hashnode';
    }

    public function category(): string
    {
        return 'creator';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://hashnode.com/utility/ajax/check-username', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Hashnode';
    }
}
