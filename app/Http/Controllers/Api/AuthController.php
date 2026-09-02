<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $user->load(['roles', 'permissions']);

        activity()->causedBy($user)->log('User registered');

        return response()->json([
            'user' => $this->formatUserAccess($user),
            'token' => $token,
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Optional: limit max devices to 3
        if ($user->tokens()->count() >= 3) {
            $user->tokens()->oldest()->first()->delete();
        }

        $token = $user->createToken(
            'react-app',
            ['*'],
            now()->addDays(7) // expires in 7 days
        );

        $user->update([
            'last_login_at' => now(),
        ]);
        $user->load(['roles', 'permissions']);

        activity()->causedBy($user)->log('User logged in');

        return response()->json([
            'message' => 'Logged in successfully',
            'user' => $this->formatUserAccess($user),
            'token' => $token->plainTextToken,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load(['roles', 'permissions']);

        return response()->json($this->fetchUserPermissions($user));
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->update($request->validated());
        $user->load(['roles', 'permissions']);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $this->formatUserAccess($user),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($request->validated('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => $request->validated('password'),
        ]);

        activity()->causedBy($user)->log('User changed password');

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        activity()->causedBy($request->user())->log('User logged out');

        return response()->json(['message' => 'Logged out']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        activity()->causedBy($request->user())->log('User logged out from all devices');

        return response()->json(['message' => 'Logged out from all devices']);
    }

    /**
     * Format the user's access information.
     *
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
     *     direct_permissions: Collection<int, Permission>,
     *     permissions: Collection<int, Permission>,
     *     effective_permissions: array<int, string>
     * }
     */
    private function formatUserAccess(User $user): array
    {

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->pluck('name')->first(),
            'email_verified_at' => $user->email_verified_at,
            'phone' => $user->phone,
            'branch_id' => $user->branch_id,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'last_login_at' => $user->last_login_at,
        ];
    }

    private function fetchUserPermissions(User $user): array
    {
        $effectivePermissionsCollection = $user->getAllPermissions()->values();

        /** @var array<int, string> $effectivePermissionNames */
        $effectivePermissionNames = $effectivePermissionsCollection
            ->pluck('name')
            ->values()
            ->all();

        return [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles,
            'direct_permissions' => $user->permissions,
            'permissions' => $effectivePermissionsCollection,
            'effective_permissions' => $effectivePermissionNames,
        ];
    }
}
