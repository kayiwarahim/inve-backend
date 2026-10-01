<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\RecoveryCode;

class MfaService
{
    public function __construct(private TotpService $totp) {}

    public function setup(User $user, string $password): array
    {
        return $user->getConnection()->transaction(function () use ($user, $password): array {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->confirmPassword($user, $password);
            abort_if($user->hasMfaEnabled(), 409, 'MFA is already enabled.');

            $secret = $this->totp->generateSecret();
            $expiresAt = now()->addMinutes(config('mfa.enrollment_minutes'));
            $user->forceFill([
                'mfa_pending_secret' => $secret,
                'mfa_pending_expires_at' => $expiresAt,
            ])->save();

            activity()->causedBy($user)->log('MFA enrollment started');

            $uri = $this->totp->provisioningUri(config('mfa.issuer'), $user->email, $secret);

            return [
                'secret' => $secret,
                'provisioning_uri' => $uri,
                'qr_code_svg' => $this->totp->qrCodeSvg($uri),
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        });
    }

    public function confirm(User $user, string $code): array
    {
        return $user->getConnection()->transaction(function () use ($user, $code): array {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_if($user->hasMfaEnabled(), 409, 'MFA is already enabled.');

            if (! $user->mfa_pending_secret || ! $user->mfa_pending_expires_at?->isFuture()) {
                throw ValidationException::withMessages(['code' => ['Enrollment expired. Start MFA setup again.']]);
            }

            $this->limitAttempts($user);
            $step = $this->verifyCode($user->mfa_pending_secret, $code);

            if ($step === false) {
                throw ValidationException::withMessages(['code' => ['Invalid authenticator code.']]);
            }

            $codes = $this->replaceRecoveryCodes($user);
            $user->forceFill([
                'mfa_secret' => $user->mfa_pending_secret,
                'mfa_confirmed_at' => now(),
                'mfa_pending_secret' => null,
                'mfa_pending_expires_at' => null,
                'mfa_last_used_step' => $step,
            ]);
            $this->clearChallenge($user);
            $user->save();
            $user->tokens()->delete();
            activity()->causedBy($user)->log('MFA enabled');

            return ['message' => 'MFA enabled. Save your recovery codes and log in again.', 'recovery_codes' => $codes];
        });
    }

    public function manage(User $user, array $credentials, bool $disable): array
    {
        return $user->getConnection()->transaction(function () use ($user, $credentials, $disable): array {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->confirmPassword($user, $credentials['password']);
            abort_unless($user->hasMfaEnabled(), 409, 'MFA is not enabled.');
            abort_if($disable && $user->requiresMfa(), 403, 'MFA is required for your role and cannot be disabled.');
            $this->limitAttempts($user);

            if (! $this->consumeFactor($user, $credentials['code'] ?? null, $credentials['recovery_code'] ?? null)) {
                throw ValidationException::withMessages(['code' => ['Invalid or previously used MFA code.']]);
            }

            $codes = [];

            if ($disable) {
                $user->forceFill([
                    'mfa_secret' => null,
                    'mfa_confirmed_at' => null,
                    'mfa_pending_secret' => null,
                    'mfa_pending_expires_at' => null,
                    'mfa_recovery_codes' => null,
                    'mfa_last_used_step' => null,
                ]);
            } else {
                $codes = $this->replaceRecoveryCodes($user);
            }

            $this->clearChallenge($user);
            $user->save();
            $user->tokens()->delete();
            activity()->causedBy($user)->log($disable ? 'MFA disabled' : 'MFA recovery codes regenerated');

            return $disable
                ? ['message' => 'MFA disabled. Please log in again.']
                : ['message' => 'Recovery codes replaced. Save these codes and log in again.', 'recovery_codes' => $codes];
        });
    }

    /**
     * Consume a factor while holding the user row lock. The caller saves the user.
     */
    public function consumeFactor(User $user, ?string $code, ?string $recoveryCode): bool
    {
        if ($recoveryCode !== null) {
            $codes = $user->mfa_recovery_codes ?? [];

            foreach ($codes as $index => $hash) {
                if (Hash::check($recoveryCode, $hash)) {
                    unset($codes[$index]);
                    $user->mfa_recovery_codes = array_values($codes);

                    return true;
                }
            }

            return false;
        }

        if ($code === null) {
            return false;
        }

        $step = $this->verifyCode($user->mfa_secret, $code, $user->mfa_last_used_step ?? 0);

        if ($step === false) {
            return false;
        }

        $user->mfa_last_used_step = $step;

        return true;
    }

    public function clearChallenge(User $user): void
    {
        $user->forceFill([
            'mfa_challenge_hash' => null,
            'mfa_challenge_expires_at' => null,
            'mfa_challenge_attempts' => 0,
        ]);
    }

    public function limitAttempts(User $user): void
    {
        $key = 'mfa-factor:'.$user->id;
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Too many MFA attempts. Try again in one minute.');
        RateLimiter::hit($key, 60);
    }

    private function verifyCode(string $secret, string $code, int $lastUsedStep = 0): int|false
    {
        return $this->totp->verify($secret, $code, now()->timestamp, $lastUsedStep);
    }

    /** @return list<string> */
    private function replaceRecoveryCodes(User $user): array
    {
        $codes = [];

        for ($index = 0; $index < config('mfa.recovery_code_count'); $index++) {
            $codes[] = RecoveryCode::generate();
        }

        $user->mfa_recovery_codes = array_map(fn (string $code): string => Hash::make($code), $codes);

        return $codes;
    }

    private function confirmPassword(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['password' => ['The current password is incorrect.']]);
        }
    }
}
