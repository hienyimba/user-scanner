<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors;

class InstagramConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'instagram';
    }

    public function category(): string
    {
        return 'social';
    }

    protected function profileUrl(string $username): string
    {
        return sprintf('https://www.instagram.com/%s/', rawurlencode($username));
    }

    protected function siteName(): string
    {
        return 'Instagram';
    }
}
