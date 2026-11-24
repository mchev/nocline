<?php

namespace App\Modules\Checks\Http\Requests;

use App\Modules\Checks\Models\Check;
use Illuminate\Foundation\Http\FormRequest;

class DetectSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Check::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $url = trim((string) $this->input('url'));

        $this->merge(['url' => $url !== '' && ! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) ? "https://{$url}" : $url]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['url' => ['required', 'url:http,https', 'max:500']];
    }
}
