<?php

use App\Models\PasswordOtp;
use App\Models\User;
use App\Services\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['mfa.required_roles' => [], 'session.driver' => 'database', 'sanctum.stateful' => ['localhost:5173'], 'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    app()->instance('testing.mfa.cookies', []);
    $this->app->bind(Google2FA::class, fn (): Google2FA => new class extends Google2FA
    {
        public function getTimestamp(): int
        {
            return intdiv(now()->timestamp, 30);
        }
    });
    $this->travelTo(now()->startOfMinute()->addSeconds(5));
});

function mfaApi(string $method, string $uri, array $data = [], ?string $token = null): TestResponse
{
    app('auth')->forgetGuards();
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');

    $response = test()->call($method, '/api'.$uri, [], $token === null ? app('testing.mfa.cookies') : [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_ORIGIN' => 'http://localhost:5173',
        'HTTP_AUTHORIZATION' => $token === null ? '' : 'Bearer '.$token,
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($data));

    if ($token === null) {
        app()->instance('testing.mfa.cookies', [config('session.cookie') => $response->getCookie(config('session.cookie'), false)?->getValue()]);
    }

    return $response;
}

function mfaCode(string $secret): string
{
    return (new Google2FA)->oathTotp($secret, intdiv(now()->timestamp, 30));
}

function mfaUser(): User
{
    return User::factory()->withMfa()->create();
}

function mfaChallenge(User $user): string
{
    return mfaApi('POST', '/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk()->assertJsonPath('mfa_required', true)->assertJsonMissingPath('token')
        ->json('challenge_token');
}

it('enrolls only after code confirmation and protects stored and logged secrets', function () {
    $user = User::factory()->create();
    $token = $user->createToken('existing')->plainTextToken;

    mfaApi('POST', '/mfa/setup', ['password' => 'wrong'], $token)->assertUnprocessable();
    $setup = mfaApi('POST', '/mfa/setup', ['password' => 'password'], $token)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $secret = $setup->json('secret');
    expect($setup->json('provisioning_uri'))->toStartWith('otpauth://totp/');
    expect($user->fresh()->hasMfaEnabled())->toBeFalse()
        ->and($user->fresh()->getRawOriginal('mfa_pending_secret'))->not->toBe($secret);

    $response = mfaApi('POST', '/mfa/confirm', ['code' => mfaCode($secret)], $token)->assertOk();
    $codes = $response->json('recovery_codes');
    $user->refresh();
    expect($codes)->toHaveCount(10)
        ->and($user->hasMfaEnabled())->toBeTrue()
        ->and($user->mfa_pending_secret)->toBeNull()
        ->and($user->tokens()->count())->toBe(0)
        ->and($user->getRawOriginal('mfa_secret'))->not->toBe($secret)
        ->and(Hash::check($codes[0], $user->mfa_recovery_codes[0]))->toBeTrue();
    expect($user->toArray())->not->toHaveKeys(['mfa_secret', 'mfa_pending_secret', 'mfa_recovery_codes', 'mfa_challenge_hash']);
    $logs = Activity::query()->get()->toJson();
    expect($logs)->not->toContain($secret)->not->toContain($codes[0])->not->toContain(mfaCode($secret));
    mfaApi('GET', '/me', [], $token)->assertUnauthorized();
});

it('rejects expired and invalid enrollment codes without enabling MFA', function () {
    $user = User::factory()->create();
    $token = $user->createToken('existing')->plainTextToken;
    $setup = mfaApi('POST', '/mfa/setup', ['password' => 'password'], $token)->assertOk();
    mfaApi('POST', '/mfa/confirm', ['code' => 'invalid'], $token)->assertUnprocessable();
    $this->travel(11)->minutes();
    mfaApi('POST', '/mfa/confirm', ['code' => mfaCode($setup->json('secret'))], $token)->assertUnprocessable();
    expect($user->fresh()->hasMfaEnabled())->toBeFalse();
});

it('requires the second factor before establishing a session and keeps permissions enforced', function () {
    $user = mfaUser();
    $challenge = mfaChallenge($user);
    expect($user->tokens()->count())->toBe(0)
        ->and($user->fresh()->last_login_at)->toBeNull();
    mfaApi('GET', '/me', [], $challenge)->assertUnauthorized();
    $response = mfaApi('POST', '/mfa/challenge', ['challenge_token' => $challenge, 'code' => mfaCode($user->mfa_secret)])->assertOk();
    $response->assertJsonMissingPath('token');
    $sessionCookie = $response->getCookie(config('session.cookie'), false)->getValue();
    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and($user->fresh()->mfa_challenge_hash)->toBeNull();
    mfaApi('GET', '/me')->assertOk()->assertJsonPath('mfa_enabled', true);
    mfaApi('GET', '/products')->assertForbidden();
    Permission::create(['name' => 'products.view', 'guard_name' => 'api']);
    $user->givePermissionTo('products.view');
    mfaApi('GET', '/products')->assertOk();
    mfaApi('POST', '/mfa/challenge', ['challenge_token' => $challenge, 'code' => mfaCode($user->mfa_secret)])->assertUnprocessable();
    expect(Activity::query()->get()->toJson())->not->toContain($challenge)->not->toContain($sessionCookie);
});

it('does not reveal an MFA challenge for an incorrect password', function () {
    $user = mfaUser();
    mfaApi('POST', '/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnauthorized()->assertJsonMissingPath('challenge_token');
    expect($user->fresh()->mfa_challenge_hash)->toBeNull();
});

it('expires challenges and supersedes an earlier login challenge', function () {
    $user = mfaUser();
    $old = mfaChallenge($user);
    $new = mfaChallenge($user);
    mfaApi('POST', '/mfa/challenge', ['challenge_token' => $old, 'code' => mfaCode($user->mfa_secret)])->assertUnprocessable();
    $this->travel(5)->minutes();
    mfaApi('POST', '/mfa/challenge', ['challenge_token' => $new, 'code' => mfaCode($user->mfa_secret)])->assertUnprocessable();
    expect($user->tokens()->count())->toBe(0);
});

it('invalidates a challenge after five failed attempts', function () {
    $user = mfaUser();
    $challenge = mfaChallenge($user);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        mfaApi('POST', '/mfa/challenge', ['challenge_token' => $challenge, 'recovery_code' => 'wrong-recovery-code'])->assertUnprocessable();
    }

    expect($user->fresh()->mfa_challenge_hash)->toBeNull();
    $this->travel(61)->seconds();
    mfaApi('POST', '/mfa/challenge', ['challenge_token' => $challenge, 'code' => mfaCode($user->mfa_secret)])->assertUnprocessable();
});

it('limits attempts across replacement challenges for the same account', function () {
    $user = mfaUser();
    $challenge = mfaChallenge($user);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        mfaApi('POST', '/mfa/challenge', ['challenge_token' => $challenge, 'recovery_code' => 'wrong'])->assertUnprocessable();
    }

    $challenge = mfaChallenge($user);
    mfaApi('POST', '/mfa/challenge', ['challenge_token' => $challenge, 'code' => mfaCode($user->mfa_secret)])->assertTooManyRequests();
});

it('prevents authenticator code reuse across separate challenges', function () {
    $user = mfaUser();
    $code = mfaCode($user->mfa_secret);
    mfaApi('POST', '/mfa/challenge', ['challenge_token' => mfaChallenge($user), 'code' => $code])->assertOk();
    Cache::flush();
    mfaApi('POST', '/mfa/challenge', ['challenge_token' => mfaChallenge($user), 'code' => $code])->assertUnprocessable();
    expect($user->tokens()->count())->toBe(0);
});

it('consumes a recovery code only once', function () {
    $user = mfaUser();
    $user->forceFill(['mfa_recovery_codes' => [Hash::make('single-use-recovery')]])->save();
    mfaApi('POST', '/mfa/challenge', ['challenge_token' => mfaChallenge($user), 'recovery_code' => 'single-use-recovery'])->assertOk();
    expect($user->fresh()->mfa_recovery_codes)->toBe([]);
    mfaApi('POST', '/mfa/challenge', ['challenge_token' => mfaChallenge($user), 'recovery_code' => 'single-use-recovery'])->assertUnprocessable();
});

it('rejects ambiguous or malformed factor input', function () {
    $user = mfaUser();
    $challenge = mfaChallenge($user);
    mfaApi('POST', '/mfa/challenge', ['challenge_token' => $challenge])->assertUnprocessable();
    mfaApi('POST', '/mfa/challenge', ['challenge_token' => $challenge, 'code' => mfaCode($user->mfa_secret), 'recovery_code' => 'another'])->assertUnprocessable();
    expect($user->tokens()->count())->toBe(0);
});

it('rejects legacy wildcard tokens for an MFA-enabled user', function () {
    $user = mfaUser();
    $legacy = $user->createToken('legacy', ['*'])->plainTextToken;
    mfaApi('GET', '/me', [], $legacy)->assertForbidden()->assertJsonPath('mfa_required', true);
    mfaApi('POST', '/logout', [], $legacy)->assertOk();
});

it('logs in without a bearer token and expires idle sessions', function () {
    $user = User::factory()->create();
    mfaApi('POST', '/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->assertJsonMissingPath('token');
    mfaApi('GET', '/me')->assertOk();
    expect($user->tokens()->count())->toBe(0);
    $this->travel(config('session.lifetime') + 1)->minutes();
    mfaApi('GET', '/me')->assertUnauthorized();
});

it('restricts required roles to enrollment until MFA is confirmed', function () {
    config(['mfa.required_roles' => ['admin']]);
    Role::create(['name' => 'admin', 'guard_name' => 'api']);
    $user = User::factory()->create();
    $user->assignRole('admin');
    $token = $user->createToken('existing')->plainTextToken;
    mfaApi('GET', '/me', [], $token)->assertForbidden()->assertJsonPath('mfa_enrollment_required', true);
    mfaApi('GET', '/mfa/status', [], $token)->assertOk()->assertJsonPath('required', true);
    mfaApi('POST', '/mfa/setup', ['password' => 'password'], $token)->assertOk();
    mfaApi('POST', '/logout', [], $token)->assertOk();
});

it('requires both factors to disable MFA and revokes tokens and challenges', function () {
    $user = mfaUser();
    $token = $user->createToken('verified', ['*', 'mfa:verified'])->plainTextToken;
    $challenge = mfaChallenge($user);
    mfaApi('DELETE', '/mfa', ['password' => 'wrong', 'code' => mfaCode($user->mfa_secret)], $token)->assertUnprocessable();
    mfaApi('DELETE', '/mfa', ['password' => 'password', 'code' => mfaCode($user->mfa_secret)], $token)->assertOk();
    expect($user->fresh()->hasMfaEnabled())->toBeFalse()
        ->and($user->fresh()->mfa_secret)->toBeNull()
        ->and($user->fresh()->mfa_challenge_hash)->toBeNull()
        ->and($user->tokens()->count())->toBe(0);
    mfaApi('POST', '/mfa/challenge', ['challenge_token' => $challenge, 'recovery_code' => 'old'])->assertUnprocessable();
});

it('does not allow mandatory MFA to be disabled', function () {
    config(['mfa.required_roles' => ['admin']]);
    Role::create(['name' => 'admin', 'guard_name' => 'api']);
    $user = mfaUser();
    $user->assignRole('admin');
    $token = $user->createToken('verified', ['*', 'mfa:verified'])->plainTextToken;
    mfaApi('DELETE', '/mfa', ['password' => 'password', 'code' => mfaCode($user->mfa_secret)], $token)->assertForbidden();
    expect($user->fresh()->hasMfaEnabled())->toBeTrue();
});

it('replaces recovery codes and requires a new login', function () {
    $user = mfaUser();
    $user->forceFill(['mfa_recovery_codes' => [Hash::make('old-recovery')]])->save();
    $token = $user->createToken('verified', ['*', 'mfa:verified'])->plainTextToken;
    $response = mfaApi('POST', '/mfa/recovery-codes/regenerate', ['password' => 'password', 'recovery_code' => 'old-recovery'], $token)->assertOk();
    expect($response->json('recovery_codes'))->toHaveCount(10)
        ->and($user->tokens()->count())->toBe(0);
    mfaApi('POST', '/mfa/challenge', ['challenge_token' => mfaChallenge($user), 'recovery_code' => 'old-recovery'])->assertUnprocessable();
});

it('preserves MFA while password reset revokes tokens and outstanding challenges', function () {
    $user = mfaUser();
    $secret = $user->mfa_secret;
    $token = $user->createToken('verified', ['*', 'mfa:verified'])->plainTextToken;
    $challenge = mfaChallenge($user);
    PasswordOtp::create(['email' => $user->email, 'otp' => Hash::make('123456'), 'expires_at' => now()->addMinutes(5)]);
    mfaApi('POST', '/password/reset', ['email' => $user->email, 'otp' => '123456', 'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertOk();
    expect($user->fresh()->hasMfaEnabled())->toBeTrue()
        ->and($user->fresh()->mfa_secret)->toBe($secret)
        ->and($user->tokens()->count())->toBe(0)
        ->and(PasswordOtp::count())->toBe(0);
    mfaApi('GET', '/me', [], $token)->assertUnauthorized();
    mfaApi('POST', '/mfa/challenge', ['challenge_token' => $challenge, 'code' => mfaCode($secret)])->assertUnprocessable();
    mfaApi('POST', '/login', ['email' => $user->email, 'password' => 'new-password'])->assertOk()->assertJsonPath('mfa_required', true)->assertJsonMissingPath('token');
});

it('invalidates sessions and pending enrollment when passwords are changed', function () {
    $user = User::factory()->create();
    $token = $user->createToken('existing')->plainTextToken;
    mfaApi('POST', '/mfa/setup', ['password' => 'password'], $token)->assertOk();
    mfaApi('POST', '/profile/change-password', ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'], $token)->assertOk();
    expect($user->tokens()->count())->toBe(0)
        ->and($user->fresh()->mfa_pending_secret)->toBeNull();
});

it('accepts an RFC 6238 test vector and prevents its replay', function () {
    $this->travelTo(Carbon::createFromTimestampUTC(59));
    $user = mfaUser();
    $user->forceFill(['mfa_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'])->save();
    $service = app(MfaService::class);
    expect($service->consumeFactor($user, '287082', null))->toBeTrue()
        ->and($service->consumeFactor($user, '287082', null))->toBeFalse();
});

it('requires authentication for all MFA management endpoints', function (string $method, string $uri) {
    mfaApi($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/mfa/status'], ['POST', '/mfa/setup'], ['POST', '/mfa/confirm'],
    ['POST', '/mfa/recovery-codes/regenerate'], ['DELETE', '/mfa'],
]);

it('does not allow setup to replace an active authenticator', function () {
    $user = mfaUser();
    $secret = $user->mfa_secret;
    $token = $user->createToken('verified', ['*', 'mfa:verified'])->plainTextToken;
    mfaApi('POST', '/mfa/setup', ['password' => 'password'], $token)->assertConflict();
    expect($user->fresh()->mfa_secret)->toBe($secret);
});

it('logs out all devices and invalidates pending MFA challenges', function () {
    $user = mfaUser();
    $token = $user->createToken('verified', ['*', 'mfa:verified'])->plainTextToken;
    $user->createToken('another-device', ['*', 'mfa:verified']);
    mfaChallenge($user);
    mfaApi('POST', '/logout-all', [], $token)->assertOk();
    expect($user->tokens()->count())->toBe(0)
        ->and($user->fresh()->mfa_challenge_hash)->toBeNull();
});

it('uses Fortify and does not register alternate web authentication routes', function () {
    expect(app(Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider::class))
        ->toBeInstanceOf(TwoFactorAuthenticationProvider::class);
    $routes = collect(Route::getRoutes())->map(fn ($route): string => $route->uri());
    expect($routes)->not->toContain('login')->not->toContain('two-factor-challenge');
});
