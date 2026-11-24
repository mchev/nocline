<?php

namespace App\Modules\Checks\Http\Requests;

use App\Modules\Checks\Support\CheckRules;
use Illuminate\Foundation\Http\FormRequest;

class CheckRequest extends FormRequest
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
        return CheckRules::make($this->input('type'));
    }

    /**
     * @return array<string, mixed>
     */
    public function checkData(): array
    {
        $data = $this->validated();
        $data['target'] ??= '';

        return $data;
    }
}
