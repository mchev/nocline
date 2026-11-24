<?php

namespace App\Modules\Probes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProbeRequest extends FormRequest
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
            'role' => ['required', 'in:primary,external'],
            'enabled' => ['boolean'],
        ];
    }
}
