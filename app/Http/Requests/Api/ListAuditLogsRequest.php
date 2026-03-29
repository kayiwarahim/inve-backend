<?php

namespace App\Http\Requests\Api;

class ListAuditLogsRequest extends PaginatedIndexRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }
}
