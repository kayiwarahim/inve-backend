<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferStockRequest extends FormRequest
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
        return [
            'from_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'to_warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id'),
                'different:from_warehouse_id',
            ],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_warehouse_id.different' => 'Transfer destination must be different from the source warehouse.',
        ];
    }
}
