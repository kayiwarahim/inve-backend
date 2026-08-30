<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'sku' => ['required', 'string', Rule::unique('products', 'sku')],
            'name' => ['required', 'string'],
            'unit' => ['required', 'string'],
            'category' => ['required', 'string', Rule::in(['input', 'output', 'both'])],
            'description' => ['nullable', 'string'],
        ];
    }
}
