<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
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
        $warehouseId = (int) $this->route('warehouse');

        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'name' => ['required', 'string'],
            'code' => ['required', 'string', Rule::unique('warehouses', 'code')->ignore($warehouseId)],
            'type' => ['required', 'string'],
        ];
    }
}
