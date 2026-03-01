<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RunScanRequest extends FormRequest
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
            'module_keys' => trim((string) $this->input('module_keys', '')) ?: null,
            'use_proxy' => filter_var($this->input('use_proxy', false), FILTER_VALIDATE_BOOL),
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
            'module_keys' => ['nullable', 'string', 'max:500'],
            'use_proxy' => ['nullable', 'boolean'],
            'proxy_list' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target.required' => 'Please enter a username or email to scan.',
            'mode.in' => 'Scan mode must be either username or email.',
        ];
    }
}
