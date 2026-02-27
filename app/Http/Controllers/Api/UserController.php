<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AssignUserRolesRequest;
use App\Http\Requests\Api\StoreUserRequest;
use App\Http\Requests\Api\SyncUserPermissionsRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): LengthAwarePaginator
    {
        return User::with(['roles', 'permissions'])
            ->paginate(20)
            ->through(function (User $user): array {
                return $this->formatUserAccess($user);
            });
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['roles', 'permissions']);

        return response()->json($this->formatUserAccess($user));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'phone' => $request->validated('phone'),
            'branch_id' => $request->validated('branch_id'),
        ]);

        $this->syncUserAccess($user, $request->validated());
        $user->load(['roles', 'permissions']);

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $this->formatUserAccess($user),
        ], 200);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $userData = collect($request->validated())
            ->except(['roles', 'permissions'])
            ->toArray();

        if ($userData !== []) {
            $user->update($userData);
        }

        $this->syncUserAccess($user, $request->validated());
        $user->load(['roles', 'permissions']);

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $this->formatUserAccess($user),
        ]);
    }

    public function accessMeta(): JsonResponse
    {
        $roles = Role::query()
            ->where('guard_name', 'api')
            ->orderBy('name')
            ->get(['id', 'name']);

        $permissions = Permission::query()
            ->where('guard_name', 'api')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function assignRole(AssignUserRolesRequest $request, User $user): JsonResponse
    {
        $user->syncRoles([$request->singleRoleName()]);
        $user->load(['roles', 'permissions']);

        return response()->json([
            'message' => 'Role assigned',
            'user' => $this->formatUserAccess($user),
        ]);
    }

    public function syncRoles(AssignUserRolesRequest $request, User $user): JsonResponse
    {
        $user->syncRoles($request->roleNames());
        $user->load(['roles', 'permissions']);

        return response()->json([
            'message' => 'Roles updated',
            'user' => $this->formatUserAccess($user),
        ]);
    }

    public function syncPermissions(SyncUserPermissionsRequest $request, User $user): JsonResponse
    {
        $user->syncPermissions($request->validated('permissions'));
        $user->load(['roles', 'permissions']);

        return response()->json([
            'message' => 'Permissions updated',
            'user' => $this->formatUserAccess($user),
        ]);
    }

    public function revokePermissions(User $user): JsonResponse
    {
        $user->syncPermissions([]);
        $user->load(['roles', 'permissions']);

        return response()->json([
            'message' => 'Permissions cleared',
            'user' => $this->formatUserAccess($user),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncUserAccess(User $user, array $payload): void
    {
        if (array_key_exists('roles', $payload)) {
            $user->syncRoles($payload['roles']);
        }

        if (array_key_exists('permissions', $payload)) {
            $user->syncPermissions($payload['permissions']);
        }
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     email_verified_at: mixed,
     *     phone: string|null,
     *     created_at: mixed,
     *     updated_at: mixed,
     *     last_login_at: mixed,
     *     branch_id: mixed,
     *     roles: Collection<int, Role>,
     *     permissions: Collection<int, Permission>,
     *     effective_permissions: array<int, string>
     * }
     */
    private function formatUserAccess(User $user): array
    {
        /** @var array<int, string> $effectivePermissions */
        $effectivePermissions = $user->getAllPermissions()
            ->pluck('name')
            ->values()
            ->all();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'phone' => $user->phone,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'last_login_at' => $user->last_login_at,
            'branch_id' => $user->branch_id,
            'roles' => $user->roles,
            'permissions' => $user->permissions,
            'effective_permissions' => $effectivePermissions,
        ];
    }
}
