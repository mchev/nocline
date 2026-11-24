<?php

namespace App\Modules\Alertes\Http\Requests;

use App\Modules\Alertes\Services\AdapterRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules are built from the fields declared by the chosen adapter. When editing, secret fields
     * may be left blank to keep the stored value.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $registry = app(AdapterRegistry::class);
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in($registry->keys())],
            'enabled' => ['boolean'],
        ];

        $type = $this->input('type');

        if (is_string($type) && $registry->has($type)) {
            foreach ($registry->class($type)::fields() as $field) {
                $keepsSecret = $this->isMethod('PATCH') && $field->secret;
                $rules[$field->name] = $keepsSecret
                    ? ['nullable', ...array_slice($field->validationRules(), 1)]
                    : $field->validationRules();
            }
        }

        return $rules;
    }

    /**
     * @return array{name: string, type: string, config: array<string, mixed>, enabled: bool}
     */
    public function channelData(): array
    {
        $data = $this->validated();
        $fields = app(AdapterRegistry::class)->class($data['type'])::fields();
        $config = [];

        foreach ($fields as $field) {
            if (isset($data[$field->name]) && $data[$field->name] !== '') {
                $config[$field->name] = $data[$field->name];
            }
        }

        return [
            'name' => $data['name'],
            'type' => $data['type'],
            'config' => $config,
            'enabled' => $data['enabled'] ?? true,
        ];
    }
}
