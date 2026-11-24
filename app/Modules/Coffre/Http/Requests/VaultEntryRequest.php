<?php

namespace App\Modules\Coffre\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VaultEntryRequest extends FormRequest
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
        $isUpdate = $this->route('entry') !== null;

        return [
            'label' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:500'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'host_id' => ['nullable', 'integer', 'exists:hosts,id'],
            'username' => ['nullable', 'string', 'max:500'],
            'secret' => [$isUpdate ? 'nullable' : 'required', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'password' => ['required', 'current_password'],
        ];
    }
}
