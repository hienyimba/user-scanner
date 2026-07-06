<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class PublicScanRequest extends AbstractScanRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'target' => $this->normalizeTarget(),
            'category' => $this->normalizeCategory(),
            'use_proxy' => $this->normalizeBoolean('use_proxy'),
            'show_hits' => $this->normalizeBoolean('show_hits'),
            'store' => $this->normalizeBoolean('store'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->baseScanRules() + [
            'target' => ['required', 'string', 'max:255'],
            'use_proxy' => ['nullable', 'boolean'],
            'show_hits' => ['nullable', 'boolean'],
            'store' => ['nullable', 'boolean'],
        ];
    }
}
