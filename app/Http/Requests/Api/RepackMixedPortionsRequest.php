<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RepackMixedPortionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'input_warehouse_id' => ['required_without:warehouse_id', 'integer', 'exists:warehouses,id'],
            'output_warehouse_id' => ['required_without:warehouse_id', 'integer', 'exists:warehouses,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'mixed_product_id' => ['required', 'integer', 'exists:products,id'],
            'mixed_batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'transformed_at' => ['required', 'date'],
            'inputs' => ['required', 'array', 'min:1'],
            'inputs.*.batch_id' => ['required', 'integer', 'exists:batches,id'],
            'inputs.*.weight_kg' => ['required', 'numeric', 'gt:0'],
            'inputs.*.remarks' => ['nullable', 'string'],
            'waste_kg' => ['nullable', 'numeric', 'gte:0'],
            'waste_product_id' => ['nullable', 'integer', 'exists:products,id'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
