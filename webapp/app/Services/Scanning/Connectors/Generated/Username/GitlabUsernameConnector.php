<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class GitlabUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'gitlab';
    }

    public function category(): string
    {
        return 'dev';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://gitlab.com/users/{user}/exists', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Gitlab';
    }
}
