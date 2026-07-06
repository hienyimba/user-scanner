<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

final class DoordashEmailValidator extends AbstractSkippedEmailValidator
{
    public function key(): string
    {
        return 'doordash';
    }

    public function category(): string
    {
        return 'other';
    }

    public function siteName(): string
    {
        return 'DoorDash';
    }

    public function siteUrl(): string
    {
        return 'https://www.doordash.com';
    }

    protected function skipReason(): string
    {
        return 'DoorDash email enrichment is intentionally disabled because phone and social-channel hints risk recovery-style behavior';
    }

    protected function blockedMetadataFields(): array
    {
        return ['phones', 'social_channels'];
    }

    protected function sensitiveFields(): array
    {
        return ['phones', 'social_channels'];
    }
}
