<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Models\User;
use App\Services\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(private AuthenticationService $authentication) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $this->authentication->endSession($request);

        $payload = (new User)->getConnection()->transaction(function () use ($request): array {
            $user = User::query()->create($request->validated());
            activity()->causedBy($user)->log('User registered');

            return $this->authentication->completeLogin($user, $request);
        });

        return response()->json($payload)->header('Cache-Control', 'no-store');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return response()->json($this->authentication->login($request->validated('email'), $request->validated('password'), $request))
            ->header('Cache-Control', 'no-store');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load(['roles', 'permissions']);

        return response()->json($this->fetchUserPermissions($user))->header('Cache-Control', 'no-store');
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

        if ($request->hasSession()) {
            $this->authentication->endSession($request);
        }

        return response()->json([
            'message' => 'Password changed successfully. Please log in again.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        activity()->causedBy($user)->log('User logged out');

        if ($request->hasSession()) {
            $this->authentication->endSession($request);
        }

        return response()->json(['message' => 'Logged out']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->getConnection()->transaction(function () use ($request): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $user->revokeAuthentication();
            $user->forceFill(['mfa_challenge_hash' => null, 'mfa_challenge_expires_at' => null, 'mfa_challenge_attempts' => 0])->save();
        });

        activity()->causedBy($request->user())->log('User logged out from all devices');

        if ($request->hasSession()) {
            $this->authentication->endSession($request);
        }

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
     *     role: string|null,
     *     mfa_enabled: bool
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
            ...$this->formatUserAccess($user),
            'roles' => $user->roles,
            'direct_permissions' => $user->permissions,
            'permissions' => $effectivePermissionsCollection,
            'effective_permissions' => $effectivePermissionNames,
        ];
    }
}
