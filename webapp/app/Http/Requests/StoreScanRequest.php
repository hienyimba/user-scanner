<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:username,email'],
            'target' => ['nullable', 'string', 'max:320', 'required_without:targets'],
            'targets' => ['nullable', 'array', 'min:1', 'required_without:target'],
            'targets.*' => ['required', 'string', 'max:320'],
            'module' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:80'],
            'proxy_profile' => ['nullable', 'string', 'max:120'],
            'verbose' => ['nullable', 'boolean'],
            'retry_limit' => ['nullable', 'integer', 'min:1', 'max:5'],
            'timeout_seconds' => ['nullable', 'integer', 'min:2', 'max:60'],
        ];
    }
}
