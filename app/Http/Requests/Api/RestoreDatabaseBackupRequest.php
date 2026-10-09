<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RestoreDatabaseBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasMfaEnabled();
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password:sanctum'],
            'confirm' => ['required', 'accepted'],
            'writers_paused' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Confirm your current password before restoring.',
            'password.current_password' => 'The password is incorrect.',
            'confirm.required' => 'Confirm replacement of the current database.',
            'confirm.accepted' => 'Confirm replacement of the current database.',
            'writers_paused.required' => 'Confirm that background workers and external database writers are paused.',
            'writers_paused.accepted' => 'Pause background workers and external database writers before restoring.',
        ];
    }
}
