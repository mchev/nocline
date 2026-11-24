<?php

namespace App\Modules\Metrics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IngestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'window_sec' => ['nullable', 'integer', 'min:1', 'max:600'],
            'agent_version' => ['nullable', 'string', 'max:32'],
            'metrics' => ['required', 'array', 'min:1', 'max:64'],
            'metrics.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_.]+$/'],
            'metrics.*.avg' => ['required', 'numeric'],
            'metrics.*.min' => ['nullable', 'numeric'],
            'metrics.*.max' => ['nullable', 'numeric'],
            'metrics.*.p95' => ['nullable', 'numeric'],
        ];
    }
}
