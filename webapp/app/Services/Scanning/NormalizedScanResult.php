<?php

declare(strict_types=1);

namespace App\Services\Scanning;

use App\Enums\ResultStatus;

class NormalizedScanResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $connectorKey,
        public readonly string $category,
        public readonly string $siteName,
        public readonly ResultStatus $status,
        public readonly string $reason = '',
        public readonly ?string $checkedUrl = null,
        public readonly string $confidence = 'mid',
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'connector_key' => $this->connectorKey,
            'category' => $this->category,
            'site_name' => $this->siteName,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'checked_url' => $this->checkedUrl,
            'confidence' => $this->confidence,
            'response_metadata' => $this->metadata,
        ];
    }
}
