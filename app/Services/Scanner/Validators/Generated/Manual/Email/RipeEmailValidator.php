<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

final class RipeEmailValidator extends AbstractSkippedEmailValidator
{
    public function key(): string
    {
        return 'ripe';
    }

    public function category(): string
    {
        return 'other';
    }

    public function siteName(): string
    {
        return 'Ripe';
    }

    public function siteUrl(): string
    {
        return 'https://www.ripe.net';
    }

    protected function skipReason(): string
    {
        return 'Ripe email enrichment is disabled until phone-hint extraction can be proven safe and non-notifying';
    }

    protected function blockedMetadataFields(): array
    {
        return ['phones'];
    }

    protected function sensitiveFields(): array
    {
        return ['phones'];
    }
}
