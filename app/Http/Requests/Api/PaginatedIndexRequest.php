<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaginatedIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 10);
    }
}
