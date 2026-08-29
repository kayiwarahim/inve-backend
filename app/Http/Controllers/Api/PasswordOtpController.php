<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SendOtpMail;
use App\Models\PasswordOtp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class PasswordOtpController extends Controller
{
    // Request OTP
    public function requestOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

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
                'mail_error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'OTP sent successfully',
        ]);
    }

    // Verify OTP
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6', // Ensure it's a 6-digit number
        ]);

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
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
            'password' => 'required|min:8|confirmed', // Strengthen min length
        ]);

        $record = PasswordOtp::where('email', $request->email)->first();

        if (! $record) {
            return response()->json(['message' => 'OTP not found'], 404);
        }

        if (now()->greaterThan($record->expires_at)) {
            $record->delete();

            return response()->json(['message' => 'OTP expired'], 400);
        }

        if (! Hash::check($request->otp, $record->otp)) {
            return response()->json(['message' => 'Invalid OTP'], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Clean up after successful reset
        PasswordOtp::where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successful']);
    }
}
