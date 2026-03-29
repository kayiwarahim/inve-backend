<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaginatedIndexRequest;
use App\Http\Requests\Api\StoreRoleRequest;
use App\Http\Requests\Api\UpdateRoleRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(PaginatedIndexRequest $request): Collection|LengthAwarePaginator
    {
        $query = Role::query()
            ->where('guard_name', 'api')
            ->with('permissions:id,name,guard_name')
            ->orderBy('name');

        if ($request->has('per_page')) {
            return $query->paginate($request->perPage());
        }

        return $query->get();
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $role = Role::query()->create([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'],
        ]);

        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($data['permissions']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => 'Role created successfully',
            'data' => $role->load('permissions:id,name,guard_name'),
        ], 200);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json(
            $role->load('permissions:id,name,guard_name'),
            200
        );
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $data = $request->validated();

        $role->update([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'],
        ]);

        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($data['permissions']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => 'Role updated successfully',
            'data' => $role->load('permissions:id,name,guard_name'),
        ], 200);
    }

    public function destroy(Role $role): JsonResponse
    {
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => 'Role deleted successfully',
        ], 200);
    }
}
