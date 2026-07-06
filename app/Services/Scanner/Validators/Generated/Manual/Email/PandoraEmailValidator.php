<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

final class PandoraEmailValidator extends AbstractSkippedEmailValidator
{
    public function key(): string
    {
        return 'pandora';
    }

    public function category(): string
    {
        return 'music';
    }

    public function siteName(): string
    {
        return 'Pandora';
    }

    public function siteUrl(): string
    {
        return 'https://www.pandora.com';
    }

    protected function skipReason(): string
    {
        return 'Pandora email enrichment is disabled until a non-notifying unauthenticated metadata path is validated';
    }

    protected function blockedMetadataFields(): array
    {
        return [
            'username',
            'display_name',
            'user_id',
            'avatar_url',
            'followers',
            'following',
            'posts_count',
            'is_private',
            'is_premium',
            'playlist_count',
            'stations',
        ];
    }
}
