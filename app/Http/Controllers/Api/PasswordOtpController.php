<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RequestPasswordOtpRequest;
use App\Http\Requests\Api\ResetPasswordOtpRequest;
use App\Http\Requests\Api\VerifyPasswordOtpRequest;
use App\Mail\SendOtpMail;
use App\Models\PasswordOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class PasswordOtpController extends Controller
{
    // Request OTP
    public function requestOtp(RequestPasswordOtpRequest $request): JsonResponse
    {
        if (! User::query()->where('email', $request->validated('email'))->exists()) {
            return response()->json(['message' => 'If the account exists, a reset code has been sent.']);
        }

        // Delete any existing OTP for this email to prevent multiples
        PasswordOtp::where('email', $request->email)->delete();

        $otp = random_int(000000, 999999);

        $record = PasswordOtp::create([
            'email' => $request->email,
            'otp' => Hash::make($otp),
            'expires_at' => now()->addMinutes(5),
        ]);

        // Send the email (queue it for async)
        try {
            Mail::to($request->email)->queue(new SendOtpMail($otp));
        } catch (\Throwable $e) {
            // Rollback on exception
            $record->delete();

            return response()->json([
                'message' => 'Unable to send a reset code. Please try again later.',
            ], 500);
        }

        return response()->json([
            'message' => 'If the account exists, a reset code has been sent.',
        ]);
    }

    // Verify OTP
    public function verifyOtp(VerifyPasswordOtpRequest $request): JsonResponse
    {
        $record = PasswordOtp::where('email', $request->email)->first();

        if (! $record) {
            return response()->json(['message' => 'OTP not found'], 404);
        }

        if (now()->greaterThan($record->expires_at)) {
            $record->delete(); // Clean up expired

            return response()->json(['message' => 'OTP expired'], 400);
        }

        if (! Hash::check($request->otp, $record->otp)) {
            return response()->json(['message' => 'Invalid OTP'], 401);
        }

        // Optionally, mark as verified or extend expiration if needed for multi-step flow

        return response()->json(['message' => 'OTP verified']);
    }

    // Reset Password
    public function resetPassword(ResetPasswordOtpRequest $request): JsonResponse
    {
        return (new User)->getConnection()->transaction(function () use ($request): JsonResponse {
            $user = User::query()->where('email', $request->validated('email'))->lockForUpdate()->first();
            $record = PasswordOtp::query()->where('email', $request->validated('email'))->lockForUpdate()->first();

            if (! $user || ! $record || ! $record->expires_at->isFuture() || ! Hash::check((string) $request->validated('otp'), $record->otp)) {
                return response()->json(['message' => 'Invalid or expired reset code.'], 422);
            }

            $user->update(['password' => $request->validated('password')]);
            PasswordOtp::query()->where('email', $user->email)->delete();
            activity()->causedBy($user)->log('User reset password');

            return response()->json(['message' => 'Password reset successful. Please log in again.']);
        });
    }
}
