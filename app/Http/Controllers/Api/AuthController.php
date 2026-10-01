<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Models\User;
use App\Services\AuthenticationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function __construct(private AuthenticationService $authentication) {}

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

        $token = $user->createToken('api-token', ['*'], now()->addDays(config('mfa.token_days')))->plainTextToken;

        $user->load(['roles', 'permissions']);

        activity()->causedBy($user)->log('User registered');

        return response()->json([
            'user' => $this->formatUserAccess($user),
            'token' => $token,
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return response()->json($this->authentication->login($request->validated('email'), $request->validated('password')))
            ->header('Cache-Control', 'no-store');
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

        $user->getConnection()->transaction(function () use ($request, $user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! Hash::check($request->validated('current_password'), $lockedUser->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['The current password is incorrect.'],
                ]);
            }

            $lockedUser->update(['password' => $request->validated('password')]);
        });

        activity()->causedBy($user)->log('User changed password');

        return response()->json([
            'message' => 'Password changed successfully. Please log in again.',
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
        $request->user()->getConnection()->transaction(function () use ($request): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $user->tokens()->delete();
            $user->forceFill(['mfa_challenge_hash' => null, 'mfa_challenge_expires_at' => null, 'mfa_challenge_attempts' => 0])->save();
        });

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
            'mfa_enabled' => $user->hasMfaEnabled(),
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
            'mfa_enabled' => $user->hasMfaEnabled(),
        ];
    }
}
