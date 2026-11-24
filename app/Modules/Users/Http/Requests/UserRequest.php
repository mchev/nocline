<?php

namespace App\Modules\Users\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isUpdate = $this->route('user') !== null;

        return [
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => [
                $isUpdate ? 'sometimes' : 'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [$isUpdate ? 'nullable' : 'required', Password::min(12)->mixedCase()->numbers()->symbols()],
        ];
    }
}
