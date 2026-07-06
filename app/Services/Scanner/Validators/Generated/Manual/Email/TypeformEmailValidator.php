<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

final class TypeformEmailValidator extends AbstractSkippedEmailValidator
{
    public function key(): string
    {
        return 'typeform';
    }

    public function category(): string
    {
        return 'other';
    }

    public function siteName(): string
    {
        return 'Typeform';
    }

    public function siteUrl(): string
    {
        return 'https://www.typeform.com';
    }

    protected function skipReason(): string
    {
        return 'Typeform email enrichment is disabled until password and SSO hints can be validated without recovery side effects';
    }

    protected function blockedMetadataFields(): array
    {
        return [
            'display_name',
            'is_verified',
            'has_password',
            'is_sso',
            'needs_password_reset',
        ];
    }

    protected function sensitiveFields(): array
    {
        return [
            'has_password',
            'is_sso',
            'needs_password_reset',
        ];
    }
}
