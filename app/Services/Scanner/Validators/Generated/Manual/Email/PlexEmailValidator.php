<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

final class PlexEmailValidator extends AbstractGravatarLinkedEmailValidator
{
    public function key(): string
    {
        return 'plex';
    }

    public function category(): string
    {
        return 'entertainment';
    }

    public function siteName(): string
    {
        return 'Plex';
    }

    public function siteUrl(): string
    {
        return 'https://www.plex.tv';
    }

    /**
     * @return array<int, string>
     */
    protected function profileHosts(): array
    {
        return ['plex.tv', 'www.plex.tv', 'watch.plex.tv', 'forums.plex.tv'];
    }
}
