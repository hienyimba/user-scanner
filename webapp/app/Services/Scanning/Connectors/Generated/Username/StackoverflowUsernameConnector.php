<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class StackoverflowUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'stackoverflow';
    }

    public function category(): string
    {
        return 'community';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://stackoverflow.com/users/filter?search={user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Stackoverflow';
    }
}
