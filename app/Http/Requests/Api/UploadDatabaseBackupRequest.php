<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadDatabaseBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['backup' => ['required', 'file', 'extensions:zip', 'mimes:zip', 'max:'.config('database-backups.max_upload_kb')]];
    }

    public function messages(): array
    {
        return [
            'backup.required' => 'Select an application-generated backup archive.',
            'backup.file' => 'The backup must be an uploaded file.',
            'backup.extensions' => 'The backup must have a .zip extension.',
            'backup.mimes' => 'The backup must be a ZIP archive.',
            'backup.max' => 'The backup exceeds the configured upload limit.',
        ];
    }
}
