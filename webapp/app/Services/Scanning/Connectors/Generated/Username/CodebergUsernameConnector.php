<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class CodebergUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'codeberg';
    }

    public function category(): string
    {
        return 'dev';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://codeberg.org/{user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Codeberg';
    }
}
