<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

final class DropboxEmailValidator extends AbstractSkippedEmailValidator
{
    public function key(): string
    {
        return 'dropbox';
    }

    public function category(): string
    {
        return 'hosting';
    }

    public function siteName(): string
    {
        return 'Dropbox';
    }

    public function siteUrl(): string
    {
        return 'https://www.dropbox.com';
    }

    protected function skipReason(): string
    {
        return 'Dropbox email enrichment is disabled until passkey hints can be gathered from a safe unauthenticated flow';
    }

    protected function blockedMetadataFields(): array
    {
        return ['has_passkey'];
    }

    protected function sensitiveFields(): array
    {
        return ['has_passkey'];
    }
}
