<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ExportStockLevelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'day' => ['nullable', 'date_format:Y-m-d'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'required_with:end_date'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'required_with:start_date', 'after_or_equal:start_date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $day = $this->input('day');
            $startDate = $this->input('start_date');
            $endDate = $this->input('end_date');

            if (filled($day) && (filled($startDate) || filled($endDate))) {
                $validator->errors()->add('day', 'day cannot be combined with start_date or end_date.');
                $validator->errors()->add('start_date', 'start_date cannot be combined with day.');
                $validator->errors()->add('end_date', 'end_date cannot be combined with day.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'start_date.required_with' => 'start_date is required when end_date is provided.',
            'end_date.after_or_equal' => 'end_date must be a date after or equal to start_date.',
            'end_date.required_with' => 'end_date is required when start_date is provided.',
        ];
    }
}
