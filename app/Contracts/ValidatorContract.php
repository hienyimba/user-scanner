<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\ScanResult;

interface ValidatorContract
{
    public function key(): string;

    public function category(): string;

    public function mode(): string; // username|email

    public function siteName(): string;

    public function siteUrl(): string;

    /** @param array<string, mixed> $options */
    public function check(string $target, array $options = []): ScanResult;
}
