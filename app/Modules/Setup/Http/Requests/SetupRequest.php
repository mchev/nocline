<?php

namespace App\Modules\Setup\Http\Requests;

use App\Support\Locales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SetupRequest extends FormRequest
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
        $dbConfigured = $this->boolean('use_configured_database');

        return [
            'use_configured_database' => ['sometimes', 'boolean'],
            'db.driver' => [$dbConfigured ? 'nullable' : 'required', 'in:sqlite,mysql,pgsql'],
            'db.database' => ['nullable', 'string', 'max:255', $dbConfigured ? 'nullable' : 'required_unless:db.driver,sqlite'],
            'db.host' => ['nullable', 'string', 'max:255', $dbConfigured ? 'nullable' : 'required_unless:db.driver,sqlite'],
            'db.port' => ['nullable', 'integer', 'between:1,65535'],
            'db.username' => ['nullable', 'string', 'max:255', $dbConfigured ? 'nullable' : 'required_unless:db.driver,sqlite'],
            'db.password' => ['nullable', 'string', 'max:255'],
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email', 'max:255'],
            'admin.password' => ['required', 'confirmed:admin.password_confirmation', Password::min(12)->mixedCase()->numbers()->symbols()],
            'instance.name' => ['required', 'string', 'max:100'],
            'instance.url' => ['required', 'url:http,https'],
            'instance.locale' => ['required', 'in:'.implode(',', array_keys(Locales::available()))],
            'instance.timezone' => ['required', 'timezone:all'],
            'mail.host' => ['nullable', 'string', 'max:255'],
            'mail.port' => ['nullable', 'integer', 'between:1,65535'],
            'mail.username' => ['nullable', 'string', 'max:255'],
            'mail.password' => ['nullable', 'string', 'max:255'],
            'mail.from' => ['nullable', 'email', 'max:255'],
        ];
    }
}
