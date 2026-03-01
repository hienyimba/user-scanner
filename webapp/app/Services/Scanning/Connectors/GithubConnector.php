<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors;

class GithubConnector extends BaseUsernameConnector
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
        return sprintf('https://github.com/%s', rawurlencode($username));
    }

    protected function siteName(): string
    {
        return 'GitHub';
    }
}
