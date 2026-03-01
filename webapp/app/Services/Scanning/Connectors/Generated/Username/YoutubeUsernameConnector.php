<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class YoutubeUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'youtube';
    }

    public function category(): string
    {
        return 'social';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://m.youtube.com/@{user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Youtube';
    }
}
