<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        $productId = (int) $this->route('product');

        return [
            'sku' => ['required', 'string', Rule::unique('products', 'sku')->ignore($productId)],
            'name' => ['required', 'string'],
            'unit' => ['required', 'string'],
            'category' => ['required', 'string', Rule::in(['input', 'output', 'both'])],
            'description' => ['nullable', 'string'],
        ];
    }
}
