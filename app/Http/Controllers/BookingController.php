<?php
// app/Http/Controllers/BookingController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\SlotLock;
use Carbon\Carbon;

class BookingController extends Controller
{
    // ── GET /api/bookings ────────────────────────────────────────────────────
    public function myBookings(Request $request)
    {
        $bookings = Booking::with('facility')
            ->where('user_id', $request->user()->id)
            ->where('is_shadow', false)
            ->where('status', 'confirmed')
            ->where('date', '>=', today())
            ->orderBy('date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($b) => $this->formatBooking($b));

        return response()->json(['bookings' => $bookings]);
    }

    // ── GET /api/bookings/{id} ───────────────────────────────────────────────
    public function show(Request $request, $id)
    {
        $booking = Booking::with('facility')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['booking' => $this->formatBooking($booking)]);
    }

    // ── POST /api/bookings ───────────────────────────────────────────────────
    public function store(Request $request)
    {
        $isAdmin = $request->user()->role === 'admin';

        $data = $request->validate([
            'facility_id' => 'required|integer|exists:facilities,id',
            'date'        => 'required|date|after_or_equal:today',
            'session'     => 'required|in:day,night',
            'slots'       => 'required|array|min:1',
            'slots.*'     => 'string',
            'paid_amount' => 'sometimes|integer|min:0',
            'guest_name'  => 'sometimes|nullable|string|max:100',
            'guest_phone' => 'sometimes|nullable|string|max:20',
            'is_hold'     => 'sometimes|boolean',
        ]);

        $facility = Facility::findOrFail($data['facility_id']);

        if (! $facility->is_active) {
            return response()->json(['message' => 'This facility is not available.'], 422);
        }

        // Validate slots belong to correct session
        $this->validateSlotSession($data['slots'], $data['session']);

        // ── CHECK 1: Direct conflict on this facility ─────────────────────────
        if ($this->hasConflict($data['facility_id'], $data['date'], $data['session'], $data['slots'])) {
            return response()->json([
                'message' => 'One or more selected slots are already booked for this facility.',
            ], 409);
        }

        // ── CHECK 2: Forward check ────────────────────────────────────────────
        // If booking Volleyball → check Badminton 1 & 2 are also free
        if (! empty($facility->linked_facility_ids)) {
            foreach ($facility->linked_facility_ids as $linkedId) {
                if ($this->hasConflict($linkedId, $data['date'], $data['session'], $data['slots'])) {
                    $linked = Facility::find($linkedId);
                    return response()->json([
                        'message' => "Cannot book {$facility->name}: {$linked->name} is already booked for one of the selected slots.",
                    ], 409);
                }
            }
        }

        // ── CHECK 3: Reverse check ────────────────────────────────────────────
        // If booking Badminton 1 or 2 → check if Volleyball is free for those slots
        // Find any facility that has THIS facility in its linked_facility_ids
        $parentFacilities = Facility::all()->filter(function ($f) use ($facility) {
            return ! empty($f->linked_facility_ids)
                && in_array($facility->id, $f->linked_facility_ids);
        });

        foreach ($parentFacilities as $parent) {
            // excludeShadow=true: only REAL bookings block, not shadows
            // e.g. shadow on Volleyball from Badminton 1 should NOT block Badminton 2
            if ($this->hasConflict($parent->id, $data['date'], $data['session'], $data['slots'], true)) {
                return response()->json([
                    'message' => "Cannot book {$facility->name}: {$parent->name} is already booked for one of the selected slots (shared court).",
                ], 409);
            }
        }

        // ── Calculate price based on day type ────────────────────────────────
        $rate       = $this->getFacilityRate($facility, $data['date'], $data['session']);
        $totalPrice = $rate * count($data['slots']);

        // ── Check slot locks (skip for admin) ────────────────────────────────
        if ($request->user()->role !== 'admin') {
            // Clean expired locks first
            SlotLock::where('expires_at', '<', Carbon::now())->delete();

            // Check if any slot is locked by another user
            foreach ($data['slots'] as $slot) {
                $lock = SlotLock::where('facility_id', $data['facility_id'])
                    ->where('date', $data['date'])
                    ->where('session', $data['session'])
                    ->where('slot', $slot)
                    ->where('user_id', '!=', $request->user()->id)
                    ->where('expires_at', '>', Carbon::now())
                    ->first();

                if ($lock) {
                    return response()->json([
                        'message' => 'One or more slots are being processed by another user. Please try again in a few minutes.',
                    ], 409);
                }
            }
        }

        // ── Validate paid amount (skip for admin) ────────────────────────────
        $paidAmount = $data['paid_amount'] ?? 0;
        $isHold     = $isAdmin && ($data['is_hold'] ?? false);

        if (!$isAdmin) {
            if ($paidAmount < 500) {
                return response()->json(['message' => 'Minimum deposit is LKR 500.'], 422);
            }
            if ($paidAmount > $totalPrice) {
                return response()->json([
                    'message' => 'Paid amount cannot exceed total price of LKR ' . number_format($totalPrice) . '.',
                ], 422);
            }
        }

        $balanceDue     = $totalPrice - $paidAmount;
        $paymentStatus  = $balanceDue === 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'pending');

        // ── Create bookings in a transaction ──────────────────────────────────
        $booking = DB::transaction(function () use ($data, $facility, $totalPrice, $request, $balanceDue, $paymentStatus, $paidAmount, $parentFacilities, $isAdmin, $isHold) {

            // Main booking
            $booking = Booking::create([
                'user_id'        => $request->user()->id,
                'facility_id'    => $facility->id,
                'date'           => $data['date'],
                'session'        => $data['session'],
                'slots'          => $data['slots'],
                'total_price'    => $totalPrice,
                'paid_amount'    => $paidAmount,
                'balance_due'    => $balanceDue,
                'payment_status' => $paymentStatus,
                'status'         => 'confirmed',
                'is_shadow'      => false,
                'guest_name'     => $isAdmin ? ($data['guest_name'] ?? null) : null,
                'guest_phone'    => $isAdmin ? ($data['guest_phone'] ?? null) : null,
                'is_hold'        => $isHold,
            ]);

            // Forward shadow bookings
            // e.g. Volleyball booked → auto-block Badminton 1 & 2
            if (! empty($facility->linked_facility_ids)) {
                foreach ($facility->linked_facility_ids as $linkedId) {
                    Booking::create([
                        'user_id'           => $request->user()->id,
                        'facility_id'       => $linkedId,
                        'date'              => $data['date'],
                        'session'           => $data['session'],
                        'slots'             => $data['slots'],
                        'total_price'       => 0,
                        'status'            => 'confirmed',
                        'parent_booking_id' => $booking->id,
                        'is_shadow'         => true,
                    ]);
                }
            }

            // Reverse shadow bookings
            // e.g. Badminton 1 booked → auto-block Volleyball
            $parentFacilities = Facility::all()->filter(function ($f) use ($facility) {
                return ! empty($f->linked_facility_ids)
                    && in_array($facility->id, $f->linked_facility_ids);
            });

            foreach ($parentFacilities as $parent) {
                // Only create reverse shadow if parent has no REAL booking yet
                // excludeShadow=true so we don't double-shadow
                $alreadyBlocked = $this->hasConflict(
                    $parent->id, $data['date'], $data['session'], $data['slots'], true
                );
                if (! $alreadyBlocked) {
                    Booking::create([
                        'user_id'           => $request->user()->id,
                        'facility_id'       => $parent->id,
                        'date'              => $data['date'],
                        'session'           => $data['session'],
                        'slots'             => $data['slots'],
                        'total_price'       => 0,
                        'status'            => 'confirmed',
                        'parent_booking_id' => $booking->id,
                        'is_shadow'         => true,
                    ]);
                }
            }

            return $booking;
        });

        $booking->load('facility');

        // Release slot locks after successful booking
        SlotLock::where('user_id', $request->user()->id)
            ->where('facility_id', $data['facility_id'])
            ->where('date', $data['date'])
            ->delete();

        return response()->json([
            'message' => $isHold ? 'Slot held successfully.' : 'Booking confirmed!',
            'booking' => $this->formatBooking($booking),
        ], 201);
    }

    // ── HELPERS ───────────────────────────────────────────────────────────────

    /**
     * Check if any of the requested slots conflict with existing REAL bookings.
     * Shadow bookings (auto-created) are excluded from conflict checks
     * because they are just placeholders, not real reservations.
     */
    /**
     * Get the correct rate for a facility based on date and session.
     * Weekday   → day_rate / night_rate
     * Weekend   → weekend_day_rate / weekend_night_rate
     * Holiday   → holiday_day_rate / holiday_night_rate
     */
    private function getFacilityRate($facility, string $date, string $session): int
    {
        $d         = \Carbon\Carbon::parse($date);
        $isWeekend = in_array($d->dayOfWeek, [0, 6]);
        $isHoliday = $this->isSriLankaHoliday($date);

        if ($isHoliday) {
            return $session === 'night'
                ? $facility->holiday_night_rate
                : $facility->holiday_day_rate;
        }

        if ($isWeekend) {
            return $session === 'night'
                ? $facility->weekend_night_rate
                : $facility->weekend_day_rate;
        }

        return $session === 'night'
            ? $facility->night_rate
            : $facility->day_rate;
    }

    private function isSriLankaHoliday(string $date): bool
    {
        return \App\Models\Holiday::where('date', $date)->exists();
    }

    private function hasConflict(int $facilityId, string $date, string $session, array $slots, bool $excludeShadow = false): bool
    {
        $query = Booking::where('facility_id', $facilityId)
            ->where('date', $date)
            ->where('session', $session)
            ->where('status', 'confirmed');

        if ($excludeShadow) {
            $query->where('is_shadow', false); // only count real bookings
        }

        $existing = $query->pluck('slots')->flatten()->toArray();

        foreach ($slots as $slot) {
            if (in_array($slot, $existing)) return true;
        }
        return false;
    }

    private function validateSlotSession(array $slots, string $session): void
    {
        $daySlots = [
            "06:00 – 07:00","07:00 – 08:00","08:00 – 09:00","09:00 – 10:00",
            "10:00 – 11:00","11:00 – 12:00","12:00 – 13:00","13:00 – 14:00",
            "14:00 – 15:00","15:00 – 16:00","16:00 – 17:00","17:00 – 18:00",
        ];
        $nightSlots = [
            "18:00 – 19:00","19:00 – 20:00","20:00 – 21:00",
            "21:00 – 22:00","22:00 – 23:00","23:00 – 23:59",
        ];

        $allowed = $session === 'day' ? $daySlots : $nightSlots;
        foreach ($slots as $slot) {
            if (! in_array($slot, $allowed)) {
                abort(422, "Slot '{$slot}' does not belong to the {$session} session.");
            }
        }
    }

    private function formatBooking(Booking $b): array
    {
        return [
            'id'             => $b->id,
            'facility'       => $b->facility?->name,
            'facility_id'    => $b->facility_id,
            'date'           => $b->date->format('D, d M Y'),
            'session'        => $b->session,
            'slots'          => $b->slots,
            'total'          => $b->total_price,
            'paid_amount'    => $b->paid_amount,
            'balance_due'    => $b->balance_due,
            'payment_status' => $b->payment_status,
            'status'         => $b->status,
            'is_today'       => $b->date->isToday(),
            'guest_name'     => $b->guest_name,
            'guest_phone'    => $b->guest_phone,
            'is_hold'        => $b->is_hold,
        ];
    }
}