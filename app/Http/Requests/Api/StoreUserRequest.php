<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'roles' => ['nullable', 'array'],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where('guard_name', 'api'),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where('guard_name', 'api'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'roles.*.exists' => 'One or more selected roles are invalid for the API guard.',
            'permissions.*.exists' => 'One or more selected permissions are invalid for the API guard.',
        ];
    }
}
