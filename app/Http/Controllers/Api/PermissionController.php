<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePermissionRequest;
use App\Http\Requests\Api\UpdatePermissionRequest;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::query()
            ->where('guard_name', 'api')
            ->orderBy('name')
            ->get();

        return response()->json($permissions, 200);
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {
        $permission = Permission::query()->create($request->validated());

        return response()->json([
            'message' => 'Permission created successfully',
            'data' => $permission,
        ], 200);
    }

    public function show(Permission $permission): JsonResponse
    {
        return response()->json($permission, 200);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        $permission->update($request->validated());

        return response()->json([
            'message' => 'Permission updated successfully',
            'data' => $permission->fresh(),
        ], 200);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully',
        ], 200);
    }
}
