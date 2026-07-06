<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;

abstract class AbstractSkippedEmailValidator implements ValidatorContract
{
    public function mode(): string
    {
        return 'email';
    }

    abstract protected function skipReason(): string;

    /**
     * @return array<int, string>
     */
    protected function blockedMetadataFields(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    protected function sensitiveFields(): array
    {
        return [];
    }

    protected function safetyRisk(): string
    {
        return 'high';
    }

    protected function requiresProxy(): bool
    {
        return true;
    }

    protected function requiresBrowser(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function additionalMetadata(): array
    {
        return [];
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $metadata = array_merge([
            'status_detail' => 'safety_blocked',
            'observed_metadata_level' => 0,
            'safety_blocked' => true,
            'safety_block_reason' => $this->skipReason(),
            'supported' => false,
            'safety_risk' => $this->safetyRisk(),
            'requires_proxy' => $this->requiresProxy(),
            'requires_browser' => $this->requiresBrowser(),
            'requires_manual_review' => true,
            'metadata_strategy' => 'safety_blocked_placeholder',
            'blocked_metadata_fields' => $this->blockedMetadataFields(),
            'sensitive_fields' => $this->sensitiveFields(),
            'sources' => [],
            'evidence' => [],
        ], $this->additionalMetadata());

        return new ScanResult(
            target: $target,
            category: $this->category(),
            siteName: $this->siteName(),
            url: $this->siteUrl(),
            status: 'Skipped',
            reason: $this->skipReason(),
            mode: $this->mode(),
            key: $this->key(),
            confidence: 0.0,
            metadata: $metadata,
        );
    }
}
