<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

final class GmailEmailValidator extends AbstractSkippedEmailValidator
{
    public function key(): string
    {
        return 'gmail';
    }

    public function category(): string
    {
        return 'other';
    }

    public function siteName(): string
    {
        return 'Gmail';
    }

    public function siteUrl(): string
    {
        return 'https://accounts.google.com';
    }

    protected function skipReason(): string
    {
        return 'Gmail email enrichment is intentionally disabled because safe non-notifying recovery-free signals are not validated';
    }

    protected function additionalMetadata(): array
    {
        return [
            'requires_proxy' => false,
        ];
    }
}
