<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreLotTransformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'process_step_id' => ['required', 'integer', 'exists:process_steps,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'transformed_at' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
            'inputs' => ['required', 'array', 'min:1'],
            'inputs.*.batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'inputs.*.source_lot_id' => ['nullable', 'integer', 'exists:source_lots,id'],
            'inputs.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'inputs.*.weight_kg' => ['required', 'numeric', 'gt:0'],
            'inputs.*.remarks' => ['nullable', 'string'],
            'outputs' => ['required', 'array', 'min:1'],
            'outputs.*.batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'outputs.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'outputs.*.weight_kg' => ['required', 'numeric', 'gt:0'],
            'outputs.*.manufactured_at' => ['nullable', 'date'],
            'outputs.*.expires_at' => ['nullable', 'date'],
            'outputs.*.remarks' => ['nullable', 'string'],
            'byproducts' => ['sometimes', 'array'],
            'byproducts.*.batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'byproducts.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'byproducts.*.weight_kg' => ['required', 'numeric', 'gt:0'],
            'byproducts.*.manufactured_at' => ['nullable', 'date'],
            'byproducts.*.expires_at' => ['nullable', 'date'],
            'byproducts.*.remarks' => ['nullable', 'string'],
            'waste' => ['sometimes', 'array'],
            'waste.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'waste.*.weight_kg' => ['required', 'numeric', 'gt:0'],
            'waste.*.remarks' => ['nullable', 'string'],
        ];
    }
}
