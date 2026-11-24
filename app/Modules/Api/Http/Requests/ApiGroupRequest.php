<?php

namespace App\Modules\Api\Http\Requests;

use App\Modules\Groups\Http\Requests\GroupRequest;
use App\Modules\Groups\Models\Group;

/**
 * Same rules as the web form; a PATCH may send only the fields it changes.
 */
class ApiGroupRequest extends GroupRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        return $group instanceof Group
            ? (bool) $this->user()?->can('update', $group)
            : (bool) $this->user()?->can('create', Group::class);
    }

    protected function prepareForValidation(): void
    {
        $group = $this->route('group');

        if ($group instanceof Group) {
            $this->merge($this->all() + ['name' => $group->name, 'parent_id' => $group->parent_id]);
        }
    }
}
