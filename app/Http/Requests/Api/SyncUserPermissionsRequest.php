<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncUserPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where('guard_name', 'api'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'permissions.required' => 'Permissions are required.',
            'permissions.*.exists' => 'One or more selected permissions are invalid for the API guard.',
        ];
    }
}
