<?php

namespace App\Modules\Groups\Http\Requests;

use App\Modules\Groups\Enums\GroupLayout;
use App\Modules\Groups\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GroupRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:groups,id'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'layout' => ['sometimes', Rule::enum(GroupLayout::class)],
            'collapsed' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'between:0,100000'],
            'channel_ids' => ['sometimes', 'array'],
            'channel_ids.*' => ['integer', 'exists:notification_channels,id'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $group = $this->route('group');

            if ($group instanceof Group && $group->wouldLoopUnder($this->integer('parent_id') ?: null)) {
                $validator->errors()->add('parent_id', __('A group cannot be placed inside itself.'));
            }
        }];
    }
}
