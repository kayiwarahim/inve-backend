<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreSourceLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'gross_car_weight_kg' => ['required', 'numeric', 'gt:0'],
            'chicken_tray_count' => ['required', 'integer', 'gt:0'],
            'tray_unit_weight_kg' => ['required', 'numeric', 'gt:0'],
            'received_weight_kg' => ['required', 'numeric', 'gt:0'],
            'received_at' => ['required', 'date'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'vehicle_number' => ['nullable', 'string', 'max:80'],
            'delivery_note_number' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:received_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'gross_car_weight_kg.required' => 'The gross car weight is required.',
            'chicken_tray_count.required' => 'The number of chicken trays is required.',
            'tray_unit_weight_kg.required' => 'The tray unit weight is required.',
        ];
    }
}
