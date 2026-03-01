<?php

declare(strict_types=1);

namespace App\Services\Scanning\Contracts;

use App\Enums\ScanType;
use App\Services\Scanning\NormalizedScanResult;

interface ConnectorInterface
{
    public function key(): string;

    public function category(): string;

    public function supports(ScanType $type): bool;

    /**
     * @param array<string, mixed> $options
     */
    public function scan(string $target, array $options = []): NormalizedScanResult;
}
