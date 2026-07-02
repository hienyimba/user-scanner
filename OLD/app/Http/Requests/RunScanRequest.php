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
        $moduleKeys = $this->input('module_keys', []);
        if (is_string($moduleKeys)) {
            $moduleKeys = array_values(array_filter(array_map('trim', explode(',', $moduleKeys))));
        }

        $this->merge([
            'target' => trim((string) $this->input('target', '')),
            'category' => trim((string) $this->input('category', '')) ?: null,
            'module_keys' => is_array($moduleKeys)
                ? array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $moduleKeys)))
                : [],
            'use_proxy' => filter_var($this->input('use_proxy', false), FILTER_VALIDATE_BOOL),
            'validate_proxies' => filter_var($this->input('validate_proxies', false), FILTER_VALIDATE_BOOL),
            'allow_loud' => filter_var($this->input('allow_loud', false), FILTER_VALIDATE_BOOL),
            'no_nsfw' => filter_var($this->input('no_nsfw', false), FILTER_VALIDATE_BOOL),
            'only_found' => filter_var($this->input('only_found', false), FILTER_VALIDATE_BOOL),
            'verbose' => filter_var($this->input('verbose', false), FILTER_VALIDATE_BOOL),
            'delay' => is_numeric($this->input('delay')) ? (float) $this->input('delay') : 0.0,
            'stop' => is_numeric($this->input('stop')) ? (int) $this->input('stop') : 100,
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
            'module_keys' => ['array'],
            'module_keys.*' => ['string', 'max:100'],
            'use_proxy' => ['nullable', 'boolean'],
            'validate_proxies' => ['nullable', 'boolean'],
            'allow_loud' => ['nullable', 'boolean'],
            'no_nsfw' => ['nullable', 'boolean'],
            'only_found' => ['nullable', 'boolean'],
            'verbose' => ['nullable', 'boolean'],
            'delay' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'stop' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'proxy_list' => ['nullable', 'string', 'max:20000'],
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
