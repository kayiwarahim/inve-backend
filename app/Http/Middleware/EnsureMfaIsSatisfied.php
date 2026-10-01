<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
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

        if ($request->routeIs('auth.logout', 'auth.logout-all')) {
            return $next($request);
        }

        if ($user->hasMfaEnabled()) {
            $token = $user->currentAccessToken();

            if (! $token instanceof PersonalAccessToken || ! in_array('mfa:verified', $token->abilities ?? [], true)) {
                return response()->json(['message' => 'Log in again and complete MFA.', 'mfa_required' => true], 403);
            }
        } elseif ($user->requiresMfa() && ! $request->routeIs('mfa.status', 'mfa.setup', 'mfa.confirm')) {
            return response()->json(['message' => 'Set up MFA before accessing the application.', 'mfa_enrollment_required' => true], 403);
        }

        return $next($request);
    }
}
