<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListAuditLogsRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(ListAuditLogsRequest $request): LengthAwarePaginator
    {
        $query = Activity::query()
            ->with('causer:id,name,email')
            ->inLog('audit')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('user_id')) {
            $query
                ->where('causer_type', (new User)->getMorphClass())
                ->where('causer_id', (int) $request->validated('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('description', $request->validated('action'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->validated('search').'%';
            $query->where(function ($nested) use ($search): void {
                $nested->where('description', 'like', $search)
                    ->orWhere('subject_type', 'like', $search)
                    ->orWhere('properties', 'like', $search)
                    ->orWhereHas('causer', function ($userQuery) use ($search): void {
                        $userQuery->where('name', 'like', $search)
                            ->orWhere('email', 'like', $search);
                    });
            });
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->validated('from').' 00:00:00');
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->validated('to').' 23:59:59');
        }

        return $query
            ->paginate($request->perPage())
            ->through(fn (Activity $activity): array => [
                'id' => $activity->id,
                'user_id' => $activity->causer_id,
                'action' => $activity->description,
                'auditable_type' => $activity->getProperty('route_action', $activity->subject_type),
                'auditable_id' => $activity->getProperty('auditable_id', $activity->subject_id),
                'old_values' => $activity->getProperty('request_payload'),
                'new_values' => $activity->getProperty('response'),
                'ip_address' => $activity->getProperty('ip_address'),
                'user_agent' => $activity->getProperty('user_agent'),
                'performed_at' => $activity->created_at,
                'created_at' => $activity->created_at,
                'updated_at' => $activity->updated_at,
                'user' => $activity->causer,
            ]);
    }
}
