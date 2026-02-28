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
            'source_lot_code' => ['required', 'string', 'max:120', 'unique:source_lots,source_lot_code'],
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
}
