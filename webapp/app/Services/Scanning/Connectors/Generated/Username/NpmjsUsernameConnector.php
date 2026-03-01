<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class NpmjsUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'npmjs';
    }

    public function category(): string
    {
        return 'dev';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://www.npmjs.com/~{user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Npmjs';
    }
}
