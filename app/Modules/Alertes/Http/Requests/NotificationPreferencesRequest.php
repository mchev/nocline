<?php

namespace App\Modules\Alertes\Http\Requests;

use App\Modules\Alertes\Enums\RenewalNotifyMode;
use App\Modules\Renewals\Models\Renewal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $modes = array_column(RenewalNotifyMode::cases(), 'value');

        $rules = [
            'incidents' => ['required', 'array'],
            'incidents.opened' => ['required', 'boolean'],
            'incidents.resolved' => ['required', 'boolean'],
            'renewals' => ['required', 'array'],
        ];

        foreach (Renewal::NOTIFY_KINDS as $kind) {
            $rules["renewals.{$kind}"] = ['required', 'string', Rule::in($modes)];
        }

        return $rules;
    }
}
