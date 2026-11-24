<?php

namespace App\Modules\Hosts\Http\Requests;

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
        ];
    }
}
