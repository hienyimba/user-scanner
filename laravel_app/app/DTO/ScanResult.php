<?php

declare(strict_types=1);

namespace App\DTO;

final class ScanResult
{
    public function __construct(
        public readonly string $target,
        public readonly string $category,
        public readonly string $siteName,
        public readonly string $url,
        public readonly string $status,
        public readonly string $reason = ''
    ) {
    }

    public function toArray(): array
    {
        return [
            'target' => $this->target,
            'category' => $this->category,
            'site_name' => $this->siteName,
            'url' => $this->url,
            'status' => $this->status,
            'reason' => $this->reason,
        ];
    }
}
