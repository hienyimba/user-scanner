<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Username;

use App\Services\Scanning\Connectors\BaseUsernameConnector;

class HuggingfaceUsernameConnector extends BaseUsernameConnector
{
    public function key(): string
    {
        return 'huggingface';
    }

    public function category(): string
    {
        return 'dev';
    }

    protected function profileUrl(string $username): string
    {
        return rtrim('https://huggingface.co/{user}', '/') . '/' . rawurlencode($username);
    }

    protected function siteName(): string
    {
        return 'Huggingface';
    }
}
