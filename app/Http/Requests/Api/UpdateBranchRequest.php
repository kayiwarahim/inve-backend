<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
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
        $branchId = (int) $this->route('branch');

        return [
            'name' => ['required', 'string'],
            'code' => ['required', 'string', Rule::unique('branches', 'code')->ignore($branchId)],
            'location' => ['nullable', 'string'],
        ];
    }
}
