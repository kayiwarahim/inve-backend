<?php

use App\Models\PasswordOtp;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'mfa.required_roles' => [],
        'session.driver' => 'database',
        'session.secure' => true,
        'sanctum.stateful' => ['localhost:5173'],
    ]);
});

function cookieApi(string $method, string $uri, array $data = [], array $cookies = [], array $server = []): TestResponse
{
    app('auth')->forgetGuards();
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');

    return test()->call($method, $uri, [], $cookies, [], array_replace([
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_ORIGIN' => 'http://localhost:5173',
        'CONTENT_TYPE' => 'application/json',
        'HTTPS' => 'on',
    ], $server), json_encode($data));
}

function responseCookies(TestResponse $response): array
{
    $cookies = [];

    foreach ($response->headers->getCookies() as $cookie) {
        $cookies[$cookie->getName()] = $cookie->getValue();
    }

    return $cookies;
}

function cookieLogin(User $user): TestResponse
{
    return cookieApi('POST', '/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
}

it('sets a secure HttpOnly cookie and regenerates the session without exposing a token', function () {
    $user = User::factory()->create();
    $anonymous = cookieApi('GET', '/sanctum/csrf-cookie')->assertNoContent();
    $response = cookieApi('POST', '/api/login', ['email' => $user->email, 'password' => 'password'], responseCookies($anonymous))
        ->assertOk()->assertJsonMissingPath('token')->assertJsonPath('user.id', $user->id);

    $cookie = $response->getCookie(config('session.cookie'));
    expect($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getExpiresTime())->toBe(0)
        ->and($cookie->getValue())->not->toBe($anonymous->getCookie(config('session.cookie'))->getValue())
        ->and($user->tokens()->count())->toBe(0);

    cookieApi('GET', '/api/me', [], responseCookies($response))
        ->assertOk()->assertJsonPath('id', $user->id)->assertJsonPath('mfa_enabled', false)
        ->assertJsonPath('last_login_at', $response->json('user.last_login_at'))
        ->assertJsonStructure(['name', 'email', 'phone', 'branch_id', 'roles', 'permissions', 'effective_permissions'])
        ->assertHeader('Cache-Control', 'no-store, private');
    expect(Activity::query()->get()->toJson())->not->toContain($cookie->getValue());
});

it('registers using a cookie session without issuing an API token', function () {
    $response = cookieApi('POST', '/api/register', ['name' => 'New Operator', 'email' => 'new@example.com', 'password' => 'password'])
        ->assertOk()->assertJsonMissingPath('token')->assertJsonPath('user.role', null);
    $user = User::query()->where('email', 'new@example.com')->firstOrFail();
    expect(Hash::check('password', $user->password))->toBeTrue()->and($user->tokens()->count())->toBe(0);
    cookieApi('GET', '/api/me', [], responseCookies($response))->assertOk()->assertJsonPath('id', $user->id);
});

it('logs out the current session and rejects a replay of its cookie', function () {
    $user = User::factory()->create();
    $cookies = responseCookies(cookieLogin($user));
    cookieApi('POST', '/api/logout', [], $cookies)->assertOk()->assertJsonPath('message', 'Logged out');
    cookieApi('GET', '/api/me', [], $cookies)->assertUnauthorized();
});

it('keeps other sessions active when logging out one device', function () {
    $user = User::factory()->create();
    $firstDevice = responseCookies(cookieLogin($user));
    $secondDevice = responseCookies(cookieLogin($user));
    cookieApi('POST', '/api/logout', [], $firstDevice)->assertOk();
    cookieApi('GET', '/api/me', [], $secondDevice)->assertOk();
});

it('logs out every session and revokes bearer tokens', function () {
    $user = User::factory()->create();
    $firstDevice = responseCookies(cookieLogin($user));
    $secondDevice = responseCookies(cookieLogin($user));
    $user->createToken('legacy');
    cookieApi('POST', '/api/logout-all', [], $firstDevice)->assertOk();
    cookieApi('GET', '/api/me', [], $firstDevice)->assertUnauthorized();
    cookieApi('GET', '/api/me', [], $secondDevice)->assertUnauthorized();
    expect($user->tokens()->count())->toBe(0);
});

it('revokes all cookie sessions on password changes', function () {
    $user = User::factory()->create();
    $firstDevice = responseCookies(cookieLogin($user));
    $secondDevice = responseCookies(cookieLogin($user));
    cookieApi('POST', '/api/profile/change-password', [
        'current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password',
    ], $firstDevice)->assertOk();
    cookieApi('GET', '/api/me', [], $firstDevice)->assertUnauthorized();
    cookieApi('GET', '/api/me', [], $secondDevice)->assertUnauthorized();
});

it('revokes cookie sessions on password reset', function () {
    $user = User::factory()->create();
    $cookies = responseCookies(cookieLogin($user));
    PasswordOtp::query()->create(['email' => $user->email, 'otp' => Hash::make('123456'), 'expires_at' => now()->addMinutes(5)]);
    cookieApi('POST', '/api/password/reset', [
        'email' => $user->email, 'otp' => '123456', 'password' => 'new-password', 'password_confirmation' => 'new-password',
    ])->assertOk();
    cookieApi('GET', '/api/me', [], $cookies)->assertUnauthorized();
});

it('requires CSRF protection for login and cookie-authenticated writes', function () {
    app()->bind(ValidateCsrfToken::class, fn (): ValidateCsrfToken => new class(app(), app('encrypter')) extends ValidateCsrfToken
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
    $user = User::factory()->create();
    cookieApi('POST', '/api/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(419);
    $csrf = cookieApi('GET', '/sanctum/csrf-cookie')->assertNoContent();
    $cookies = responseCookies($csrf);
    $login = cookieApi('POST', '/api/login', ['email' => $user->email, 'password' => 'password'], $cookies, [
        'HTTP_X_XSRF_TOKEN' => $cookies['XSRF-TOKEN'],
    ])->assertOk();
    $cookies = responseCookies($login);
    cookieApi('POST', '/api/logout', [], $cookies)->assertStatus(419);
    cookieApi('POST', '/api/logout', [], $cookies, ['HTTP_X_XSRF_TOKEN' => $cookies['XSRF-TOKEN']])->assertOk();
});

it('rejects session login from an unconfigured origin', function () {
    $user = User::factory()->create();
    cookieApi('POST', '/api/login', ['email' => $user->email, 'password' => 'password'], [], [
        'HTTP_ORIGIN' => 'https://untrusted.example',
    ])->assertStatus(419)->assertJsonMissingPath('token');
    expect($user->tokens()->count())->toBe(0)->and($user->fresh()->last_login_at)->toBeNull();
});

it('issues only a short-lived enrollment token before mandatory MFA enrollment', function () {
    config(['mfa.required_roles' => ['admin']]);
    Role::query()->create(['name' => 'admin', 'guard_name' => 'api']);
    $user = User::factory()->create();
    $user->assignRole('admin');
    $response = cookieLogin($user)->assertJsonPath('mfa_enrollment_required', true);
    $token = $user->tokens()->sole();
    expect($token->abilities)->toBe(['mfa:enroll'])
        ->and($token->expires_at->diffInSeconds(now(), true))->toBeLessThanOrEqual(600);
    cookieApi('GET', '/api/me', [], responseCookies($response))->assertUnauthorized();
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$response->json('token')];
    cookieApi('GET', '/api/mfa/status', [], [], $server)->assertOk();
    cookieApi('POST', '/api/mfa/setup', ['password' => 'password'], [], $server)->assertOk();
    cookieApi('GET', '/api/me', [], [], $server)->assertForbidden()->assertJsonPath('mfa_enrollment_required', true);
    $this->travel(11)->minutes();
    cookieApi('GET', '/api/mfa/status', [], [], $server)->assertUnauthorized();
});

it('enforces enrollment token scoping even after the mandatory role is removed', function () {
    Permission::query()->create(['name' => 'products.view', 'guard_name' => 'api']);
    $user = User::factory()->create();
    $user->givePermissionTo('products.view');
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$user->createToken('enrollment', ['mfa:enroll'])->plainTextToken];
    cookieApi('GET', '/api/products', [], [], $server)->assertForbidden();
    cookieApi('GET', '/api/me', [], [], $server)->assertForbidden();
    cookieApi('POST', '/api/profile/change-password', [], [], $server)->assertForbidden();
    cookieApi('POST', '/api/logout-all', [], [], $server)->assertForbidden();
    cookieApi('GET', '/api/mfa/status', [], [], $server)->assertOk();
    cookieApi('POST', '/api/logout', [], [], $server)->assertOk();
});

it('establishes an MFA-verified cookie session only after the challenge succeeds', function () {
    $user = User::factory()->withMfa()->create(['mfa_recovery_codes' => [Hash::make('login-recovery')]]);
    $login = cookieLogin($user)->assertJsonPath('mfa_required', true)->assertJsonMissingPath('token');
    cookieApi('GET', '/api/me', [], responseCookies($login))->assertUnauthorized();
    $response = cookieApi('POST', '/api/mfa/challenge', [
        'challenge_token' => $login->json('challenge_token'), 'recovery_code' => 'login-recovery',
    ], responseCookies($login))->assertOk()->assertJsonMissingPath('token');
    cookieApi('GET', '/api/me', [], responseCookies($response))->assertOk()->assertJsonPath('mfa_enabled', true);
    expect($user->tokens()->count())->toBe(0);
});

it('revokes every existing cookie session when MFA enrollment is confirmed', function () {
    $this->travelTo(now()->startOfMinute()->addSeconds(5));
    $user = User::factory()->create();
    $firstDevice = responseCookies(cookieLogin($user));
    $secondDevice = responseCookies(cookieLogin($user));
    $setup = cookieApi('POST', '/api/mfa/setup', ['password' => 'password'], $firstDevice)->assertOk();
    $code = (new Google2FA)->oathTotp($setup->json('secret'), intdiv(now()->timestamp, 30));
    cookieApi('POST', '/api/mfa/confirm', ['code' => $code], $firstDevice)->assertOk();
    cookieApi('GET', '/api/me', [], $firstDevice)->assertUnauthorized();
    cookieApi('GET', '/api/me', [], $secondDevice)->assertUnauthorized();
    cookieLogin($user)->assertJsonPath('mfa_required', true);
});

it('revokes browser sessions when MFA credentials are changed', function (bool $disable) {
    $user = User::factory()->withMfa()->create([
        'mfa_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), ['first-login', 'second-login', 'manage']),
    ]);
    $challenge = cookieLogin($user)->json('challenge_token');
    $firstDevice = responseCookies(cookieApi('POST', '/api/mfa/challenge', [
        'challenge_token' => $challenge, 'recovery_code' => 'first-login',
    ])->assertOk());
    $challenge = cookieLogin($user)->json('challenge_token');
    $secondDevice = responseCookies(cookieApi('POST', '/api/mfa/challenge', [
        'challenge_token' => $challenge, 'recovery_code' => 'second-login',
    ])->assertOk());
    cookieApi($disable ? 'DELETE' : 'POST', $disable ? '/api/mfa' : '/api/mfa/recovery-codes/regenerate', [
        'password' => 'password', 'recovery_code' => 'manage',
    ], $firstDevice)->assertOk();
    cookieApi('GET', '/api/me', [], $firstDevice)->assertUnauthorized();
    cookieApi('GET', '/api/me', [], $secondDevice)->assertUnauthorized();
})->with(['disable MFA' => true, 'regenerate recovery codes' => false]);

it('expires existing bearer tokens according to the Sanctum expiration setting', function () {
    $user = User::factory()->create();
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$user->createToken('legacy')->plainTextToken];
    cookieApi('GET', '/api/me', [], [], $server)->assertOk();
    $this->travel(config('sanctum.expiration') + 1)->minutes();
    cookieApi('GET', '/api/me', [], [], $server)->assertUnauthorized();
});

it('applies five attempts per minute to public auth and password endpoints', function (string $uri) {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        cookieApi('POST', $uri)->assertUnprocessable();
    }

    cookieApi('POST', $uri)->assertTooManyRequests()->assertHeader('Retry-After');
})->with([
    'login' => '/api/login',
    'mfa challenge' => '/api/mfa/challenge',
    'request reset code' => '/api/password/request-otp',
    'verify reset code' => '/api/password/verify-otp',
    'reset password' => '/api/password/reset',
]);

it('applies five attempts per minute to MFA management endpoints', function () {
    $cookies = responseCookies(cookieLogin(User::factory()->create()));

    for ($attempt = 0; $attempt < 5; $attempt++) {
        cookieApi('POST', '/api/mfa/setup', ['password' => 'wrong'], $cookies)->assertUnprocessable();
    }

    cookieApi('POST', '/api/mfa/setup', ['password' => 'wrong'], $cookies)->assertTooManyRequests();
});

it('returns credentialed CORS headers only for configured origins', function () {
    cookieApi('OPTIONS', '/api/login', [], [], ['HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST'])
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')->assertHeader('Access-Control-Allow-Credentials', 'true');
    cookieApi('OPTIONS', '/api/login', [], [], [
        'HTTP_ORIGIN' => 'https://untrusted.example', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ])->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('sends a restrictive CSP on successful and failed responses', function (string $uri) {
    cookieApi('GET', $uri)->assertHeader('Content-Security-Policy', config('security.content_security_policy'));
    expect(config('security.content_security_policy'))->toContain("script-src 'self'")->not->toContain("script-src 'self' 'unsafe-inline'");
})->with(['/', '/api/me', '/api/not-a-route']);
