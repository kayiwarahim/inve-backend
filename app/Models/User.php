<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\CausesActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use CausesActivity, HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'branch_id',
    ];

    protected string $guard_name = 'api';

    protected $attributes = [
        'mfa_challenge_attempts' => 0,
    ];

    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            if ($user->isDirty('password')) {
                $user->tokens()->delete();
                $user->setRememberToken(Str::random(60));
                $user->forceFill([
                    'mfa_challenge_hash' => null,
                    'mfa_challenge_expires_at' => null,
                    'mfa_challenge_attempts' => 0,
                    'mfa_pending_secret' => null,
                    'mfa_pending_expires_at' => null,
                ]);
            }
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_pending_secret',
        'mfa_pending_expires_at',
        'mfa_recovery_codes',
        'mfa_last_used_step',
        'mfa_challenge_hash',
        'mfa_challenge_expires_at',
        'mfa_challenge_attempts',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'mfa_secret' => 'encrypted',
            'mfa_pending_secret' => 'encrypted',
            'mfa_recovery_codes' => 'array',
            'mfa_confirmed_at' => 'datetime',
            'mfa_pending_expires_at' => 'datetime',
            'mfa_challenge_expires_at' => 'datetime',
            'mfa_last_used_step' => 'integer',
            'mfa_challenge_attempts' => 'integer',
        ];
    }

    public function receivedSourceLots(): HasMany
    {
        return $this->hasMany(SourceLot::class, 'received_by');
    }

    public function hasMfaEnabled(): bool
    {
        return $this->mfa_confirmed_at !== null && $this->mfa_secret !== null;
    }

    public function requiresMfa(): bool
    {
        $roles = config('mfa.required_roles', []);

        return $roles !== [] && $this->hasAnyRole($roles);
    }

    public function revokeAuthentication(): void
    {
        $this->tokens()->delete();
        $this->setRememberToken(Str::random(60));
        $this->save();
    }

    public function performedLotTransformations(): HasMany
    {
        return $this->hasMany(LotTransformation::class, 'performed_by');
    }
}
