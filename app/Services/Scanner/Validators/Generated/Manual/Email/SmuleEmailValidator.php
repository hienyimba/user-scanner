<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

final class SmuleEmailValidator extends AbstractGravatarLinkedEmailValidator
{
    public function key(): string
    {
        return 'smule';
    }

    public function category(): string
    {
        return 'music';
    }

    public function siteName(): string
    {
        return 'Smule';
    }

    public function siteUrl(): string
    {
        return 'https://www.smule.com';
    }

    /**
     * @return array<int, string>
     */
    protected function profileHosts(): array
    {
        return ['smule.com', 'www.smule.com'];
    }
}
