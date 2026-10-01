<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MfaManageRequest extends FormRequest
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
            'password' => ['required', 'string', 'max:1024'],
            'code' => ['required_without:recovery_code', 'prohibits:recovery_code', 'nullable', 'string', 'regex:/^[0-9]{6}$/'],
            'recovery_code' => ['required_without:code', 'prohibits:code', 'nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Confirm your current password.',
            'code.required_without' => 'Enter an authenticator code or a recovery code.',
            'code.regex' => 'The authenticator code must contain six digits.',
            'code.prohibits' => 'Use either an authenticator code or a recovery code.',
            'recovery_code.required_without' => 'Enter an authenticator code or a recovery code.',
            'recovery_code.prohibits' => 'Use either an authenticator code or a recovery code.',
        ];
    }
}
