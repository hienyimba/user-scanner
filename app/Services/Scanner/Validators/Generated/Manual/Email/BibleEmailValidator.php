<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

final class BibleEmailValidator extends AbstractGravatarLinkedEmailValidator
{
    public function key(): string
    {
        return 'bible';
    }

    public function category(): string
    {
        return 'community';
    }

    public function siteName(): string
    {
        return 'Bible';
    }

    public function siteUrl(): string
    {
        return 'https://www.bible.com';
    }

    /**
     * @return array<int, string>
     */
    protected function profileHosts(): array
    {
        return ['bible.com', 'www.bible.com'];
    }
}
