<?php
// app/Http/Controllers/SlotLockController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SlotLock;
use Carbon\Carbon;

class SlotLockController extends Controller
{
    // ── POST /api/slots/lock ──────────────────────────────────────────────────
    public function lock(Request $request)
    {
        $isAdmin = $request->user()->role === 'admin';

        $data = $request->validate([
            'facility_id' => 'required|integer',
            'date'        => 'required|date',
            'session'     => 'required|string',
            'slots'       => 'required|array|min:1',
            'slots.*'     => 'string',
            'duration'    => 'sometimes|integer|min:1|max:60', // max 60 mins for admin hold
        ]);

        $userId    = $request->user()->id;
        // Admin hold → 60 mins, regular user → max 10 mins
        $minutes   = $isAdmin
            ? ($data['duration'] ?? 10)
            : min($data['duration'] ?? 10, 10);
        $expiresAt = Carbon::now()->addMinutes($minutes);

        // Remove any existing locks by this user for this facility/date/session
        SlotLock::where('user_id', $userId)
            ->where('facility_id', $data['facility_id'])
            ->where('date', $data['date'])
            ->where('session', $data['session'])
            ->delete();

        // Remove expired locks
        SlotLock::where('expires_at', '<', Carbon::now())->delete();

        // Check if any slot is already locked by another user
        foreach ($data['slots'] as $slot) {
            $existing = SlotLock::where('facility_id', $data['facility_id'])
                ->where('date', $data['date'])
                ->where('session', $data['session'])
                ->where('slot', $slot)
                ->where('user_id', '!=', $userId)
                ->where('expires_at', '>', Carbon::now())
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'One or more slots are being processed by another user. Please try again.',
                    'locked'  => true,
                ], 409);
            }
        }

        // Create locks for all selected slots
        foreach ($data['slots'] as $slot) {
            SlotLock::create([
                'user_id'     => $userId,
                'facility_id' => $data['facility_id'],
                'date'        => $data['date'],
                'session'     => $data['session'],
                'slot'        => $slot,
                'expires_at'  => $expiresAt,
            ]);
        }

        return response()->json([
            'message'    => 'Slots locked successfully.',
            'expires_at' => $expiresAt->toISOString(),
            'expires_in' => $minutes * 60, // seconds
        ]);
    }

    // ── DELETE /api/slots/lock ────────────────────────────────────────────────
    public function unlock(Request $request)
    {
        $userId = $request->user()->id;

        SlotLock::where('user_id', $userId)->delete();

        return response()->json(['message' => 'Slots unlocked.']);
    }
}