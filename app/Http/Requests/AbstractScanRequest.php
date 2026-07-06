<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class AbstractScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function normalizeTarget(?string $key = 'target'): ?string
    {
        if ($key === null) {
            return null;
        }

        return trim((string) $this->input($key, ''));
    }

    protected function normalizeCategory(): ?string
    {
        return trim((string) $this->input('category', '')) ?: null;
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeModuleKeys(): array
    {
        $moduleKeys = $this->input('module_keys', []);
        if (is_string($moduleKeys)) {
            $moduleKeys = explode(',', $moduleKeys);
        }

        if (!is_array($moduleKeys)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $moduleKeys,
        )));
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeTargets(): array
    {
        $targets = $this->input('targets', []);
        if (!is_array($targets)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $targets,
        )));
    }

    protected function normalizeBoolean(string $key, bool $default = false): bool
    {
        return filter_var($this->input($key, $default), FILTER_VALIDATE_BOOL);
    }

    protected function normalizeFloat(string $key, float $default): float
    {
        return is_numeric($this->input($key)) ? (float) $this->input($key) : $default;
    }

    protected function normalizeInt(string $key, int $default): int
    {
        return is_numeric($this->input($key)) ? (int) $this->input($key) : $default;
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseScanRules(bool $includeModuleKeys = false): array
    {
        $rules = [
            'mode' => ['required', 'in:username,email'],
            'category' => ['nullable', 'string', 'max:64'],
        ];

        if ($includeModuleKeys) {
            $rules['module_keys'] = ['array'];
            $rules['module_keys.*'] = ['string', 'max:100'];
        }

        return $rules;
    }
}
