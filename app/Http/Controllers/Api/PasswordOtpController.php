<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PasswordOtp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail;

class PasswordOtpController extends Controller
{
    // Request OTP
    public function requestOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $otp = random_int(100000, 999999);

        PasswordOtp::where('email', $request->email)->delete();

        $record = PasswordOtp::create([
            'email' => $request->email,
            'otp' => Hash::make($otp),
            'expires_at' => now()->addMinutes(5),
        ]);

        return response()->json([
            'created' => $record
        ]);

        // PasswordOtp::create([
        //     'email' => $request->email,
        //     'otp' => Hash::make($otp),
        //     'expires_at' => now()->addMinutes(5),
        // ]);

        // try {
        //     Mail::to($request->email)->send(new SendOtpMail($otp));

        //     if (count(Mail::failures()) > 0) {
        //         return response()->json([
        //             'mail_failure' => Mail::failures()
        //         ], 500);
        //     }

        // } catch (\Throwable $e) {
        //     return response()->json([
        //         'mail_error' => $e->getMessage()
        //     ], 500);
        // }

        // return response()->json([
        //     'message' => 'OTP sent',
        //     'otp' => $otp, // remove in production
        // ]);
    }

    // Verify OTP
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required',
        ]);

        $record = PasswordOtp::where('email', $request->email)->first();

        if (! $record) {
            return response()->json(['message' => 'OTP not found'], 404);
        }

        if (now()->greaterThan($record->expires_at)) {
            return response()->json(['message' => 'OTP expired'], 400);
        }

        if (! Hash::check($request->otp, $record->otp)) {
            return response()->json(['message' => 'Invalid OTP'], 401);
        }

        return response()->json(['message' => 'OTP verified']);
    }

    // Reset Password
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required',
            'password' => 'required|min:6|confirmed',
        ]);

        $record = PasswordOtp::where('email', $request->email)->first();

        if (! $record || ! Hash::check($request->otp, $record->otp)) {
            return response()->json(['message' => 'Invalid OTP'], 401);
        }

        if (now()->greaterThan($record->expires_at)) {
            return response()->json(['message' => 'OTP expired'], 400);
        }

        $user = User::where('email', $request->email)->first();

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        PasswordOtp::where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successful']);
    }
}
