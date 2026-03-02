<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListAuditLogsRequest;
use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditLogController extends Controller
{
    public function index(ListAuditLogsRequest $request): LengthAwarePaginator
    {
        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('performed_at')
            ->orderByDesc('id');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->validated('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->validated('action'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->validated('search').'%';
            $query->where(function ($nested) use ($search): void {
                $nested->where('action', 'like', $search)
                    ->orWhere('auditable_type', 'like', $search)
                    ->orWhere('ip_address', 'like', $search)
                    ->orWhereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('name', 'like', $search)
                            ->orWhere('email', 'like', $search);
                    });
            });
        }

        if ($request->filled('from')) {
            $query->where('performed_at', '>=', $request->validated('from').' 00:00:00');
        }

        if ($request->filled('to')) {
            $query->where('performed_at', '<=', $request->validated('to').' 23:59:59');
        }

        return $query->paginate($request->perPage());
    }
}
