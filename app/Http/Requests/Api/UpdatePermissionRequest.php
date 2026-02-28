<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Permission $permission */
        $permission = $this->route('permission');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')
                    ->where('guard_name', 'api')
                    ->ignore($permission->id),
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
