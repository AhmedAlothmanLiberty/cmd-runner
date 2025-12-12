<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'automation', 'super-admin']) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('automations', 'slug')],
            'command' => ['required', 'string', 'max:255'],
            'cron_expression' => ['required', 'string', 'max:255'],
            'timezone' => ['nullable', 'timezone:all'],
            'daily_time' => ['nullable', 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],
            'timeout_seconds' => ['nullable', 'integer', 'min:1'],
            'run_via' => ['required', Rule::in(['artisan', 'later'])],
            'notify_on_fail' => ['sometimes', 'boolean'],
        ];
    }
}
