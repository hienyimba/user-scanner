<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

final class FoursquareEmailValidator extends AbstractGravatarLinkedEmailValidator
{
    public function key(): string
    {
        return 'foursquare';
    }

    public function category(): string
    {
        return 'social';
    }

    public function siteName(): string
    {
        return 'Foursquare';
    }

    public function siteUrl(): string
    {
        return 'https://foursquare.com';
    }

    /**
     * @return array<int, string>
     */
    protected function profileHosts(): array
    {
        return ['foursquare.com'];
    }
}
