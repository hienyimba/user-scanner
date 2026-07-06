<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class RunScanRequest extends AbstractScanRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'target' => $this->normalizeTarget(),
            'category' => $this->normalizeCategory(),
            'module_keys' => $this->normalizeModuleKeys(),
            'use_proxy' => $this->normalizeBoolean('use_proxy'),
            'validate_proxies' => $this->normalizeBoolean('validate_proxies'),
            'allow_loud' => $this->normalizeBoolean('allow_loud'),
            'no_nsfw' => $this->normalizeBoolean('no_nsfw'),
            'only_found' => $this->normalizeBoolean('only_found'),
            'verbose' => $this->normalizeBoolean('verbose'),
            'delay' => $this->normalizeFloat('delay', 0.0),
            'stop' => $this->normalizeInt('stop', 100),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->baseScanRules(includeModuleKeys: true) + [
            'target' => ['required', 'string', 'max:255'],
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
