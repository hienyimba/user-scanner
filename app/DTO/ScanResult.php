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
        public readonly string $reason = '',
        public readonly string $extra = '',
        public readonly string $mode = 'username',
        public readonly string $key = '',
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            target: (string) ($data['target'] ?? ''),
            category: strtolower((string) ($data['category'] ?? '')),
            siteName: (string) ($data['site_name'] ?? ''),
            url: (string) ($data['url'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            reason: (string) ($data['reason'] ?? ''),
            extra: (string) ($data['extra'] ?? ''),
            mode: (string) ($data['mode'] ?? 'username'),
            key: (string) ($data['key'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'target' => $this->target,
            'category' => strtolower($this->category),
            'site_name' => $this->siteName,
            'url' => $this->url,
            'status' => $this->status,
            'reason' => $this->reason,
            'extra' => $this->extra,
            'mode' => $this->mode,
            'key' => $this->key,
        ];
    }
}
