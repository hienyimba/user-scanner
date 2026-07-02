<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PublicScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'target' => trim((string) $this->input('target', '')),
            'category' => trim((string) $this->input('category', '')) ?: null,
            'use_proxy' => filter_var($this->input('use_proxy', false), FILTER_VALIDATE_BOOL),
            'show_hits' => filter_var($this->input('show_hits', false), FILTER_VALIDATE_BOOL),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:username,email'],
            'target' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            'use_proxy' => ['nullable', 'boolean'],
            'show_hits' => ['nullable', 'boolean'],
        ];
    }
}
