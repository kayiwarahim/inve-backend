<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    public function __construct(private MfaService $mfa) {}

    public function login(string $email, string $password): array
    {
        return (new User)->getConnection()->transaction(function () use ($email, $password): array {
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

            return $this->issueToken($user);
        });
    }

    public function challenge(string $challenge, ?string $code, ?string $recoveryCode): array
    {
        $result = (new User)->getConnection()->transaction(function () use ($challenge, $code, $recoveryCode): ?array {
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

            return $this->issueToken($user);
        });

        if ($result === null) {
            throw ValidationException::withMessages(['code' => ['Invalid or expired MFA challenge or code. Start a new login if necessary.']]);
        }

        return $result;
    }

    /**
     * The caller must hold the user row lock and have completed authentication.
     */
    private function issueToken(User $user): array
    {
        $user->tokens()->whereNotNull('expires_at')->where('expires_at', '<=', now())->delete();

        while ($user->tokens()->count() >= 3) {
            $user->tokens()->oldest('id')->first()->delete();
        }

        $token = $user->createToken('react-app', $user->hasMfaEnabled() ? ['*', 'mfa:verified'] : ['*'], now()->addDays(config('mfa.token_days')));
        $user->forceFill(['last_login_at' => now()])->save();
        $user->load('roles');
        activity()->causedBy($user)->log('User logged in');

        return [
            'message' => 'Logged in successfully',
            'mfa_required' => false,
            'mfa_enrollment_required' => $user->requiresMfa() && ! $user->hasMfaEnabled(),
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
            'token' => $token->plainTextToken,
        ];
    }
}
