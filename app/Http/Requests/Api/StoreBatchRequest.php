<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatchRequest extends FormRequest
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
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'manufactured_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:manufactured_at'],
        ];
    }
}
