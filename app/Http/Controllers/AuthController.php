<?php
// app/Http/Controllers/AuthController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class AuthController extends Controller
{
    // ── POST /api/register ───────────────────────────────────────────────────
    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'username'   => 'required|string|max:50|unique:users',
            'email'      => 'required|email|unique:users',
            'phone'      => 'required|string|max:10',
            'password'   => 'required|string|min:6|confirmed', // needs password_confirmation field
            'role'       => 'sometimes|in:user,admin',         // only accepted if provided
            'admin_code' => 'sometimes|string',                // secret code check in logic
        ]);

        // Admin secret code check
        if (isset($data['role']) && $data['role'] === 'admin') {
            $secret = config('app.admin_secret', 'horizon@admin2025');
            if (($data['admin_code'] ?? '') !== $secret) {
                return response()->json([
                    'message' => 'Invalid admin secret code.',
                ], 403);
            }
        }

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'username'   => $data['username'],
            'email'      => $data['email'],
            'phone'      => $data['phone'],
            'password'   => Hash::make($data['password']),
            'role'       => $data['role'] ?? 'user',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'user'    => $this->formatUser($user),
            'token'   => $token,
        ], 201);
    }

    // ── POST /api/login ──────────────────────────────────────────────────────
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Incorrect email or password.',
            ], 401);
        }

        // Revoke old tokens and issue a fresh one
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user'    => $this->formatUser($user),
            'token'   => $token,
        ]);
    }

    // ── POST /api/logout ─────────────────────────────────────────────────────
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    // ── GET /api/me ──────────────────────────────────────────────────────────
    public function me(Request $request)
    {
        return response()->json(['user' => $this->formatUser($request->user())]);
    }

    // ── Helper ───────────────────────────────────────────────────────────────
    private function formatUser(User $user): array
    {
        return [
            'id'         => $user->id,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'full_name'  => $user->full_name,
            'username'   => $user->username,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'role'       => $user->role,
        ];
    }
}