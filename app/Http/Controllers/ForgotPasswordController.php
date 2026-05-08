<?php
// app/Http/Controllers/ForgotPasswordController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ForgotPasswordController extends Controller
{
    // ── POST /api/forgot-password ─────────────────────────────────────────────
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Always return success even if email not found (security best practice)
        if (! $user) {
            return response()->json([
                'message' => 'If this email exists, a reset link has been sent.',
            ]);
        }

        // Generate token
        $token = Str::random(64);

        // Delete old tokens for this email
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Store new token
        DB::table('password_reset_tokens')->insert([
            'email'      => $request->email,
            'token'      => Hash::make($token),
            'created_at' => Carbon::now(),
        ]);

        // Send email
        $resetUrl = config('app.frontend_url', 'http://localhost:5173') . '/reset-password?token=' . $token . '&email=' . urlencode($request->email);

        Mail::send('emails.reset-password', [
            'user'     => $user,
            'resetUrl' => $resetUrl,
        ], function ($message) use ($user) {
            $message->to($user->email)
                    ->subject('Reset Your Horizon Indoor Password');
        });

        return response()->json([
            'message' => 'If this email exists, a reset link has been sent.',
        ]);
    }

    // ── POST /api/reset-password ──────────────────────────────────────────────
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email',
            'token'                 => 'required|string',
            'password'              => 'required|string|min:6|confirmed',
        ]);

        // Find token record
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Invalid or expired reset link.'], 422);
        }

        // Check token is valid
        if (! Hash::check($request->token, $record->token)) {
            return response()->json(['message' => 'Invalid or expired reset link.'], 422);
        }

        // Check token not expired (60 minutes)
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => 'Reset link has expired. Please request a new one.'], 422);
        }

        // Update password
        $user = User::where('email', $request->email)->first();
        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Delete token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Revoke all tokens (logout all devices)
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully! Please login with your new password.',
        ]);
    }
}