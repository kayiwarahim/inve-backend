<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProcessStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('process_steps', 'code')->ignore($this->route('processStep')?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'sequence' => ['required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
