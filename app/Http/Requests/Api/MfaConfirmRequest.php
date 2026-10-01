<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MfaConfirmRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Enter the code from your authenticator app.',
            'code.regex' => 'The authenticator code must contain six digits.',
        ];
    }
}
