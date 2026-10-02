<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'max:1024'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Enter your name.',
            'email.required' => 'Enter your email address.',
            'email.unique' => 'This email address is already registered.',
            'password.required' => 'Enter your password.',
            'password.min' => 'Your password must contain at least 6 characters.',
        ];
    }
}
