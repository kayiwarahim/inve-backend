<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')->where('guard_name', 'api'),
            ],
            'guard_name' => ['sometimes', 'in:api'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('guard_name')) {
            $this->merge([
                'guard_name' => 'api',
            ]);
        }
    }
}
