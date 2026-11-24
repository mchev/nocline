<?php

namespace App\Modules\Api\Http\Requests;

use App\Modules\Checks\Http\Requests\CheckRequest;
use App\Modules\Checks\Models\Check;

/**
 * Same rules as the web form. A PATCH is validated against the stored check completed with the
 * submitted fields, so conditional rules (target / host per type) stay correct for partial payloads.
 */
class ApiCheckRequest extends CheckRequest
{
    public function authorize(): bool
    {
        $check = $this->route('check');

        return $check instanceof Check
            ? (bool) $this->user()?->can('update', $check)
            : (bool) $this->user()?->can('create', Check::class);
    }

    protected function prepareForValidation(): void
    {
        $check = $this->route('check');

        if (! $check instanceof Check) {
            return;
        }

        $this->merge($this->all() + [
            'name' => $check->name,
            'type' => $check->type->value,
            'target' => $check->target,
            'host_id' => $check->host_id,
            'interval_sec' => $check->interval_sec,
        ]);
    }
}
