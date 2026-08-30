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
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'input_warehouse_id' => ['required_without:warehouse_id', 'integer', 'exists:warehouses,id'],
            'output_warehouse_id' => ['required_without:warehouse_id', 'integer', 'exists:warehouses,id'],
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
            'outputs.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'outputs.*.weight_kg' => ['required', 'numeric', 'gt:0'],
            'outputs.*.manufactured_at' => ['nullable', 'date'],
            'outputs.*.expires_at' => ['nullable', 'date'],
            'outputs.*.remarks' => ['nullable', 'string'],
            'byproducts' => ['sometimes', 'array'],
            'byproducts.*.batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'byproducts.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
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

    public function after(): array
    {
        return [
            function ($validator) {
                foreach ($this->input('outputs', []) as $index => $output) {
                    $hasBatch = ! empty($output['batch_id'] ?? null);
                    $hasProduct = ! empty($output['product_id'] ?? null);

                    if (! $hasBatch && ! $hasProduct) {
                        $validator->errors()->add("outputs.$index.product_id", 'The outputs.'.$index.'.product_id field is required when no batch_id is supplied.');
                    }
                }

                foreach ($this->input('byproducts', []) as $index => $byproduct) {
                    $hasBatch = ! empty($byproduct['batch_id'] ?? null);
                    $hasProduct = ! empty($byproduct['product_id'] ?? null);

                    if (! $hasBatch && ! $hasProduct) {
                        $validator->errors()->add("byproducts.$index.product_id", 'The byproducts.'.$index.'.product_id field is required when no batch_id is supplied.');
                    }
                }
            },
        ];
    }
}
