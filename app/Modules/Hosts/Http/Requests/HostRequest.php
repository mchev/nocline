<?php

namespace App\Modules\Hosts\Http\Requests;

use App\Modules\Hosts\Support\HostMetricAlerts;
use Illuminate\Foundation\Http\FormRequest;

class HostRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'interval_sec' => ['sometimes', 'integer', 'min:30', 'max:86400'],
            'timeout_sec' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'failure_threshold' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'agent_auto_update' => ['sometimes', 'boolean'],
            ...HostMetricAlerts::validationRules(),
        ];
    }
}
