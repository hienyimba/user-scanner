<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class GithubUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'github';
    }

    public function category(): string
    {
        return 'dev';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://github.com/signup_check/username?value={user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Github';
    }
}
