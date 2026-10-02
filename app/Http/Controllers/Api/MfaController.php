<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MfaChallengeRequest;
use App\Http\Requests\Api\MfaConfirmRequest;
use App\Http\Requests\Api\MfaManageRequest;
use App\Http\Requests\Api\MfaSetupRequest;
use App\Services\AuthenticationService;
use App\Services\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MfaController extends Controller
{
    public function __construct(private MfaService $mfa, private AuthenticationService $authentication) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->respond([
            'enabled' => $user->hasMfaEnabled(),
            'required' => $user->requiresMfa(),
            'confirmed_at' => $user->mfa_confirmed_at,
            'recovery_codes_remaining' => count($user->mfa_recovery_codes ?? []),
        ]);
    }

    public function setup(MfaSetupRequest $request): JsonResponse
    {
        return $this->respond($this->mfa->setup($request->user(), $request->validated('password')));
    }

    public function confirm(MfaConfirmRequest $request): JsonResponse
    {
        $payload = $this->mfa->confirm($request->user(), $request->validated('code'));
        $this->endSession($request);

        return $this->respond($payload);
    }

    public function challenge(MfaChallengeRequest $request): JsonResponse
    {
        return $this->respond($this->authentication->challenge(
            $request->validated('challenge_token'),
            $request->validated('code'),
            $request->validated('recovery_code'),
            $request,
        ));
    }

    public function regenerate(MfaManageRequest $request): JsonResponse
    {
        $payload = $this->mfa->manage($request->user(), $request->validated(), false);
        $this->endSession($request);

        return $this->respond($payload);
    }

    public function destroy(MfaManageRequest $request): JsonResponse
    {
        $payload = $this->mfa->manage($request->user(), $request->validated(), true);
        $this->endSession($request);

        return $this->respond($payload);
    }

    private function endSession(Request $request): void
    {
        if ($request->hasSession()) {
            $this->authentication->endSession($request);
        }
    }

    private function respond(array $payload): JsonResponse
    {
        return response()->json($payload)->header('Cache-Control', 'no-store');
    }
}
