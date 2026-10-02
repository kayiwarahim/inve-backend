<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    public function __construct(private MfaService $mfa) {}

    public function login(string $email, string $password, Request $request): array
    {
        $this->endSession($request);

        return (new User)->getConnection()->transaction(function () use ($email, $password, $request): array {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            abort_if(! $user || ! Hash::check($password, $user->password), 401, 'Invalid credentials');

            if ($user->hasMfaEnabled()) {
                $challenge = Str::random(64);
                $expiresAt = now()->addMinutes(config('mfa.challenge_minutes'));
                $user->forceFill([
                    'mfa_challenge_hash' => hash('sha256', $challenge),
                    'mfa_challenge_expires_at' => $expiresAt,
                    'mfa_challenge_attempts' => 0,
                ])->save();

                return [
                    'mfa_required' => true,
                    'challenge_token' => $challenge,
                    'expires_at' => $expiresAt->toIso8601String(),
                ];
            }

            return $this->completeLogin($user, $request);
        });
    }

    public function challenge(string $challenge, ?string $code, ?string $recoveryCode, Request $request): array
    {
        $this->endSession($request);

        $result = (new User)->getConnection()->transaction(function () use ($challenge, $code, $recoveryCode, $request): ?array {
            $user = User::query()->where('mfa_challenge_hash', hash('sha256', $challenge))->lockForUpdate()->first();

            if (! $user || ! $user->hasMfaEnabled() || ! $user->mfa_challenge_expires_at?->isFuture()) {
                return null;
            }

            $this->mfa->limitAttempts($user);
            $user->mfa_challenge_attempts++;

            if (! $this->mfa->consumeFactor($user, $code, $recoveryCode)) {
                if ($user->mfa_challenge_attempts >= config('mfa.challenge_attempts')) {
                    $this->mfa->clearChallenge($user);
                }

                $user->save();
                activity()->causedBy($user)->log('MFA challenge failed');

                return null;
            }

            $this->mfa->clearChallenge($user);
            $user->save();
            activity()->causedBy($user)->log('MFA challenge completed');

            return $this->completeLogin($user, $request);
        });

        if ($result === null) {
            throw ValidationException::withMessages(['code' => ['Invalid or expired MFA challenge or code. Start a new login if necessary.']]);
        }

        return $result;
    }

    /**
     * The caller must hold the user row lock and have completed authentication.
     */
    public function completeLogin(User $user, Request $request): array
    {
        abort_unless($request->hasSession(), 419, 'Initialize a session from a configured frontend origin before logging in.');

        $user->tokens()->whereNotNull('expires_at')->where('expires_at', '<=', now())->delete();
        $enrollmentRequired = $user->requiresMfa() && ! $user->hasMfaEnabled();

        if (! $enrollmentRequired) {
            if (! $user->getRememberToken()) {
                $user->setRememberToken(Str::random(60));
            }

            $user->forceFill(['last_login_at' => now()])->save();
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
            $request->session()->put([
                'auth_session_token' => $user->getRememberToken(),
                'mfa_verified' => $user->hasMfaEnabled(),
            ]);
            activity()->causedBy($user)->log('User logged in');
        }

        $user->load('roles');

        $payload = [
            'message' => 'Logged in successfully',
            'mfa_required' => false,
            'mfa_enrollment_required' => $enrollmentRequired,
            'user' => [
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
            ],
        ];

        if ($enrollmentRequired) {
            $user->tokens()->where('name', 'mfa-enrollment')->delete();
            $payload['token'] = $user->createToken('mfa-enrollment', ['mfa:enroll'], now()->addMinutes(config('mfa.enrollment_minutes')))->plainTextToken;
        }

        return $payload;
    }

    public function endSession(Request $request): void
    {
        abort_unless($request->hasSession(), 419, 'Initialize a session from a configured frontend origin before logging in.');

        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
