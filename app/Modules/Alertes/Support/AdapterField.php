<?php

namespace App\Modules\Alertes\Support;

/**
 * One configuration input of an adapter. `secret` fields are never sent back to the browser.
 */
final readonly class AdapterField
{
    /**
     * @param  'text'|'email'|'url'|'password'|'textarea'  $type
     * @param  array<int, mixed>  $rules  extra Laravel validation rules
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type = 'text',
        public bool $required = true,
        public bool $secret = false,
        public ?string $help = null,
        public ?string $placeholder = null,
        public array $rules = [],
    ) {}

    /**
     * @return array<int, mixed>
     */
    public function validationRules(): array
    {
        $base = match ($this->type) {
            'email' => ['email', 'max:255'],
            'url' => ['url:https,http', 'max:1000'],
            default => ['string', 'max:2000'],
        };

        return [$this->required ? 'required' : 'nullable', ...$base, ...$this->rules];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => __($this->label),
            'type' => $this->type,
            'required' => $this->required,
            'secret' => $this->secret,
            'help' => $this->help ? __($this->help) : null,
            'placeholder' => $this->placeholder,
        ];
    }
}
