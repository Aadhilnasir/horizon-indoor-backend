<?php
// app/Http/Controllers/FacilityController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Facility;
use App\Models\Booking;
use App\Models\SlotLock;
use App\Models\FacilityBlock;
use Carbon\Carbon;

class FacilityController extends Controller
{
    // ── GET /api/facilities ──────────────────────────────────────────────────
    // Public — only returns ACTIVE facilities (for booking page and home page)
    public function index()
    {
        $facilities = Facility::where('is_active', true)->get();
        return response()->json(['facilities' => $facilities]);
    }

    // ── GET /api/admin/slot-info?facility_id=&date=&session=&slot= ───────────
    // Admin only — returns who booked a specific slot
    public function slotInfo(Request $request)
    {
        $request->validate([
            'facility_id' => 'required|integer|exists:facilities,id',
            'date'        => 'required|date',
            'session'     => 'required|in:day,night',
            'slot'        => 'required|string',
        ]);

        $booking = \App\Models\Booking::with('user')
            ->where('facility_id', $request->facility_id)
            ->where('date',        $request->date)
            ->where('session',     $request->session)
            ->where('status',      'confirmed')
            ->where('is_shadow',   false)
            ->get()
            ->first(function ($b) use ($request) {
                return in_array($request->slot, $b->slots);
            });

        // Also check shadow bookings if no real booking found
        if (! $booking) {
            $shadow = \App\Models\Booking::with(['user', 'parentBooking.user', 'parentBooking.facility'])
                ->where('facility_id', $request->facility_id)
                ->where('date',        $request->date)
                ->where('session',     $request->session)
                ->where('status',      'confirmed')
                ->where('is_shadow',   true)
                ->get()
                ->first(function ($b) use ($request) {
                    return in_array($request->slot, $b->slots);
                });

            if ($shadow && $shadow->parentBooking) {
                $parent = $shadow->parentBooking;
                // Use guest name if admin booked for a walk-in customer
                $displayName  = $parent->guest_name
                    ? $parent->guest_name
                    : ($parent->user?->first_name . ' ' . $parent->user?->last_name);
                $displayPhone = $parent->guest_name
                    ? ($parent->guest_phone ?? '—')
                    : ($parent->user?->phone ?? '—');
                $displayEmail = $parent->guest_name ? '(Walk-in customer)' : $parent->user?->email;

                return response()->json([
                    'booked'     => true,
                    'slot'       => $request->slot,
                    'is_shadow'  => true,
                    'is_hold'    => (bool) $parent->is_hold,
                    'is_guest'   => (bool) $parent->guest_name,
                    'booked_via' => $parent->facility?->name,
                    'user' => [
                        'name'  => $displayName,
                        'phone' => $displayPhone,
                        'email' => $displayEmail,
                    ],
                ]);
            }

            return response()->json(['booked' => false]);
        }

        // Use guest name if admin booked for a walk-in customer
        $displayName  = $booking->guest_name
            ? $booking->guest_name
            : ($booking->user?->first_name . ' ' . $booking->user?->last_name);
        $displayPhone = $booking->guest_name
            ? ($booking->guest_phone ?? '—')
            : ($booking->user?->phone ?? '—');
        $displayEmail = $booking->guest_name ? '(Walk-in customer)' : $booking->user?->email;

        return response()->json([
            'booked'     => true,
            'slot'       => $request->slot,
            'is_shadow'  => false,
            'is_hold'    => (bool) $booking->is_hold,
            'is_guest'   => (bool) $booking->guest_name,
            'booked_via' => null,
            'user' => [
                'name'  => $displayName,
                'phone' => $displayPhone,
                'email' => $displayEmail,
            ],
        ]);
    }


    // Admin only — returns ALL facilities including inactive ones
    public function adminIndex()
    {
        $facilities = Facility::orderBy('id')->get();
        return response()->json(['facilities' => $facilities]);
    }

    // ── GET /api/facilities/{id} ─────────────────────────────────────────────
    public function show($id)
    {
        $facility = Facility::findOrFail($id);

        return response()->json(['facility' => $facility]);
    }

    // ── GET /api/facilities/{id}/slots?date=2025-04-26&session=day ──────────
    // Returns which slots are already booked for a given facility, date, session.
    // The frontend uses this to mark slots as "Booked".
    public function availableSlots(Request $request, $id)
    {
        $request->validate([
            'date'    => 'required|date',
            'session' => 'required|in:day,night',
        ]);

        $facility = Facility::findOrFail($id);

        $bookedSlots = Booking::where('facility_id', $id)
            ->where('date',    $request->date)
            ->where('session', $request->session)
            ->where('status',  'confirmed')
            ->pluck('slots')
            ->flatten()
            ->unique()
            ->values()
            ->toArray();

        // Determine day type and pick correct rate
        $date      = \Carbon\Carbon::parse($request->date);
        $isWeekend = in_array($date->dayOfWeek, [0, 6]);
        $isHoliday = \App\Models\Holiday::where('date', $request->date)->exists();
        $dayType   = $isHoliday ? 'holiday' : ($isWeekend ? 'weekend' : 'weekday');

        if ($isHoliday) {
            $dayRate   = $facility->holiday_day_rate;
            $nightRate = $facility->holiday_night_rate;
        } elseif ($isWeekend) {
            $dayRate   = $facility->weekend_day_rate;
            $nightRate = $facility->weekend_night_rate;
        } else {
            $dayRate   = $facility->day_rate;
            $nightRate = $facility->night_rate;
        }

        // Check if facility is blocked on this date
        $block = FacilityBlock::where('facility_id', $id)
            ->where('start_date', '<=', $request->date)
            ->where('end_date',   '>=', $request->date)
            ->first();

        if ($block) {
            return response()->json([
                'facility_id'  => (int) $id,
                'date'         => $request->date,
                'session'      => $request->session,
                'booked_slots' => [],
                'locked_slots' => [],
                'blocked'      => true,
                'block_reason' => $block->reason,
                'day_type'     => $dayType,
                'day_rate'     => $dayRate,
                'night_rate'   => $nightRate,
            ]);
        }

        // Get locked slots (being processed by other users)
        SlotLock::where('expires_at', '<', Carbon::now())->delete();
        
        $currentUserId = auth('sanctum')->id();
        $authUser      = auth('sanctum')->user();
        $isAdmin       = $authUser && $authUser->role === 'admin';

        // Get locked slots — both admin and user see them
        // Admin sees them but can still book (with warning)
        $lockedSlots = SlotLock::where('facility_id', $id)
            ->where('date', $request->date)
            ->where('session', $request->session)
            ->where('expires_at', '>', Carbon::now())
            ->when($currentUserId, fn($q) => $q->where('user_id', '!=', $currentUserId))
            ->pluck('slot')
            ->unique()
            ->values()
            ->toArray();

        return response()->json([
            'facility_id'  => (int) $id,
            'date'         => $request->date,
            'session'      => $request->session,
            'booked_slots' => $bookedSlots,
            'locked_slots' => $lockedSlots,
            'blocked'      => false,
            'block_reason' => null,
            'day_type'     => $dayType,
            'day_rate'     => $dayRate,
            'night_rate'   => $nightRate,
        ]);
    }



    // ── POST /api/admin/facilities  (admin only) ─────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:150',
            'icon'                => 'sometimes|string|max:10',
            'tag'                 => 'sometimes|string|max:100',
            'day_rate'            => 'required|integer|min:0',
            'night_rate'          => 'required|integer|min:0',
            'linked_facility_ids' => 'sometimes|array',
            'linked_facility_ids.*' => 'integer|exists:facilities,id',
        ]);

        $facility = Facility::create($data);

        return response()->json(['facility' => $facility], 201);
    }

    // ── PUT /api/admin/facilities/{id}  (admin only) ─────────────────────────
    public function update(Request $request, $id)
    {
        $facility = Facility::findOrFail($id);

        $data = $request->validate([
            'name'                => 'sometimes|string|max:150',
            'icon'                => 'sometimes|string|max:10',
            'tag'                 => 'sometimes|string|max:100',
            'day_rate'            => 'sometimes|integer|min:0',
            'night_rate'          => 'sometimes|integer|min:0',
            'linked_facility_ids' => 'sometimes|nullable|array',
            'linked_facility_ids.*' => 'integer|exists:facilities,id',
        ]);

        $facility->update($data);

        return response()->json(['facility' => $facility]);
    }

    // ── PATCH /api/admin/facilities/{id}/toggle  (admin only) ────────────────
    public function toggleActive($id)
    {
        $facility = Facility::findOrFail($id);
        $facility->update(['is_active' => ! $facility->is_active]);

        return response()->json([
            'facility'  => $facility,
            'is_active' => $facility->is_active,
        ]);
    }

    // ── DELETE /api/admin/facilities/{id}  (admin only) ──────────────────────
    public function destroy($id)
    {
        $facility = Facility::findOrFail($id);
        $facility->delete();

        return response()->json(['message' => 'Facility deleted.']);
    }
}