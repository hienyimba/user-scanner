<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

final class VscoEmailValidator extends AbstractGravatarLinkedEmailValidator
{
    public function key(): string
    {
        return 'vsco';
    }

    public function category(): string
    {
        return 'creator';
    }

    public function siteName(): string
    {
        return 'VSCO';
    }

    public function siteUrl(): string
    {
        return 'https://vsco.co';
    }

    /**
     * @return array<int, string>
     */
    protected function profileHosts(): array
    {
        return ['vsco.co'];
    }
}
