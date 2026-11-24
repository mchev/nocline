<?php

namespace App\Modules\Maintenance\Http\Requests;

use App\Modules\Maintenance\Enums\MaintenanceRecurrence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MaintenanceWindowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManage() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id', 'prohibits:check_id'],
            'check_id' => ['nullable', 'integer', 'exists:checks,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'recurrence' => ['required', Rule::enum(MaintenanceRecurrence::class)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $recurrence = MaintenanceRecurrence::from($this->string('recurrence')->toString());
            $period = $recurrence->periodDays();
            $duration = CarbonImmutable::parse($this->string('starts_at')->toString())
                ->diffInSeconds(CarbonImmutable::parse($this->string('ends_at')->toString()));

            if ($period > 0 && $duration >= $period * 86400) {
                $validator->errors()->add('ends_at', __('A repeating maintenance must be shorter than its repetition.'));
            }
        }];
    }

    /**
     * Validated attributes with the dates converted to the app timezone.
     *
     * @return array<string, mixed>
     */
    public function windowData(): array
    {
        $data = $this->validated();
        $timezone = config('app.timezone');

        foreach (['starts_at', 'ends_at'] as $field) {
            $data[$field] = CarbonImmutable::parse($data[$field])->setTimezone($timezone);
        }

        return $data + ['group_id' => null, 'check_id' => null];
    }
}
