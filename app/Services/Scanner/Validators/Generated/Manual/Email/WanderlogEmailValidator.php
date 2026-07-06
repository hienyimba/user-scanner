<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

final class WanderlogEmailValidator extends AbstractGravatarLinkedEmailValidator
{
    public function key(): string
    {
        return 'wanderlog';
    }

    public function category(): string
    {
        return 'travel';
    }

    public function siteName(): string
    {
        return 'Wanderlog';
    }

    public function siteUrl(): string
    {
        return 'https://wanderlog.com';
    }

    /**
     * @return array<int, string>
     */
    protected function profileHosts(): array
    {
        return ['wanderlog.com', 'www.wanderlog.com'];
    }
}
