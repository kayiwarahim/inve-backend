<?php

namespace App\Http\Requests\Api;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where('guard_name', 'api'),
            ],
            'permissions' => ['sometimes', 'array'],
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
