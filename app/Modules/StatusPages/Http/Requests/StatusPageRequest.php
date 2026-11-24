<?php

namespace App\Modules\StatusPages\Http\Requests;

use App\Modules\StatusPages\Models\StatusPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StatusPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');

        if (! is_string($slug) || trim($slug) === '') {
            $this->merge(['slug' => Str::slug($this->string('title')->toString())]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $page = $this->route('page');

        return [
            'title' => ['required', 'string', 'max:120'],
            'slug' => [
                'required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('status_pages', 'slug')->ignore($page instanceof StatusPage ? $page->id : null),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp', 'max:1024'],
            'remove_logo' => ['sometimes', 'boolean'],
            'show_incidents' => ['sometimes', 'boolean'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Attributes for the model (the logo file is handled by the controller).
     *
     * @return array<string, mixed>
     */
    public function pageData(): array
    {
        return collect($this->validated())->except(['logo', 'remove_logo'])->all();
    }
}
