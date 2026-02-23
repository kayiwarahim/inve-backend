<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => [
                'nullable',
                'string',
                Rule::exists('roles', 'name')->where('guard_name', 'api'),
            ],
            'roles' => ['nullable', 'array', 'min:1'],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where('guard_name', 'api'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'role.exists' => 'The selected role is invalid for the API guard.',
            'roles.min' => 'At least one role must be provided.',
            'roles.*.exists' => 'One or more selected roles are invalid for the API guard.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('role') && ! $this->filled('roles')) {
                $validator->errors()->add('roles', 'Provide either role or roles.');
            }
        });
    }

    public function roleNames(): array
    {
        $roles = $this->validated('roles', []);

        if ($this->filled('role')) {
            $roles[] = $this->validated('role');
        }

        return array_values(array_unique(array_filter($roles)));
    }

    public function singleRoleName(): string
    {
        $singleRole = $this->validated('role');

        if (is_string($singleRole) && $singleRole !== '') {
            return $singleRole;
        }

        return $this->roleNames()[0];
    }
}
