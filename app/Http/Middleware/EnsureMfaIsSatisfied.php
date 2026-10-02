<?php

namespace App\Http\Middleware;

use App\Services\AuthenticationService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaIsSatisfied
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if ($token instanceof TransientToken && ! $this->hasValidSession($request)) {
            if ($request->hasSession()) {
                app(AuthenticationService::class)->endSession($request);
            }

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($token instanceof PersonalAccessToken && in_array('mfa:enroll', $token->abilities ?? [], true) && ! $request->routeIs('mfa.status', 'mfa.setup', 'mfa.confirm', 'auth.logout')) {
            return response()->json(['message' => 'Set up MFA before accessing the application.', 'mfa_enrollment_required' => true], 403);
        }

        if ($request->routeIs('auth.logout', 'auth.logout-all')) {
            return $next($request);
        }

        if ($user->hasMfaEnabled()) {
            $verified = $token instanceof TransientToken
                ? $request->session()->get('mfa_verified') === true
                : $token instanceof PersonalAccessToken && in_array('mfa:verified', $token->abilities ?? [], true);

            if (! $verified) {
                return response()->json(['message' => 'Log in again and complete MFA.', 'mfa_required' => true], 403);
            }
        } elseif ($user->requiresMfa() && ! $request->routeIs('mfa.status', 'mfa.setup', 'mfa.confirm')) {
            return response()->json(['message' => 'Set up MFA before accessing the application.', 'mfa_enrollment_required' => true], 403);
        }

        return $next($request);
    }

    private function hasValidSession(Request $request): bool
    {
        if (! $request->hasSession() || ! $request->user()->getRememberToken()) {
            return false;
        }

        return hash_equals($request->user()->getRememberToken(), (string) $request->session()->get('auth_session_token', ''));
    }
}
