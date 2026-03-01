<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class InstagramUsernameConnector extends BaseUsernameConnector
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
        return rtrim('https://www.instagram.com/api/v1/users/web_profile_info/', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Instagram';
    }
}
