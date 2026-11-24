<?php

namespace App\Modules\Api\Http\Requests;

use App\Modules\Api\Models\ApiToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', ApiToken::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'access' => ['required', Rule::in(['read', 'write'])],
            'expires_in_days' => ['nullable', 'integer', 'between:1,730'],
        ];
    }
}
