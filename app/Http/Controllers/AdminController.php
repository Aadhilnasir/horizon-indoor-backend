<?php
// app/Http/Controllers/AdminController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\User;
use App\Models\Facility;

class AdminController extends Controller
{
    // ── GET /api/admin/stats?period=today|week|month|year|all ────────────────
    public function stats(Request $request)
    {
        $period = $request->get('period', 'all');

        // Base query
        $base = Booking::where('is_shadow', false)->where('status', 'confirmed');

        // Apply period filter
        $filtered = clone $base;
        switch ($period) {
            case 'today':
                $filtered->whereDate('date', today());
                break;
            case 'week':
                $filtered->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $filtered->whereMonth('date', now()->month)->whereYear('date', now()->year);
                break;
            case 'year':
                $filtered->whereYear('date', now()->year);
                break;
            default:
                // all time — no filter
                break;
        }

        $totalBookings   = $filtered->count();
        $totalRevenue    = $filtered->sum('total_price');
        $totalUsers      = User::where('role', 'user')->count();
        $totalFacilities = Facility::count();

        // Today's bookings always fixed
        $todayBookings = Booking::whereDate('date', today())
            ->where('is_shadow', false)
            ->where('status', 'confirmed')
            ->count();

        return response()->json([
            'total_bookings'   => $totalBookings,
            'total_revenue'    => $totalRevenue,
            'total_users'      => $totalUsers,
            'total_facilities' => $totalFacilities,
            'today_bookings'   => $todayBookings,
            'period'           => $period,
        ]);
    }

    // ── GET /api/admin/bookings ──────────────────────────────────────────────
    public function allBookings(Request $request)
    {
        $query = Booking::with(['user', 'facility'])
            ->where('is_shadow', false)
            ->where('status',    'confirmed')
            ->where('date',      '>=', today()); // hide past bookings, keep today + future

        // Filters
        if ($request->has('facility_id')) {
            $query->where('facility_id', $request->facility_id);
        }
        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        }
        if ($request->has('session')) {
            $query->where('session', $request->session);
        }

        $bookings = $query->orderBy('date', 'asc')
            ->orderBy('session', 'asc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($b) => [
                'id'             => $b->id,
                'user'           => $b->user?->username,
                'user_email'     => $b->user?->email,
                'facility'       => $b->facility?->name,
                'facility_id'    => $b->facility_id,
                'date'           => $b->date->format('D, d M Y'),
                'raw_date'       => $b->date->format('Y-m-d'),
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
                'is_hold'        => (bool) $b->is_hold,
                'created_at'     => $b->created_at->format('d M Y H:i'),
            ]);

        return response()->json(['bookings' => $bookings]);
    }

    // ── DELETE /api/admin/bookings/{id} ──────────────────────────────────────
    // Cancels the booking AND all shadow (linked) bookings created with it.
    public function cancelBooking($id)
    {
        $booking = Booking::findOrFail($id);

        // Cancel shadow bookings (e.g. Badminton 1 & 2 auto-blocked by Volleyball)
        Booking::where('parent_booking_id', $id)->update(['status' => 'cancelled']);

        $booking->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Booking cancelled successfully.']);
    }

    // ── GET /api/admin/users ─────────────────────────────────────────────────
    public function allUsers()
    {
        $users = User::withCount('bookings')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($u) => [
                'id'             => $u->id,
                'full_name'      => "{$u->first_name} {$u->last_name}",
                'username'       => $u->username,
                'email'          => $u->email,
                'phone'          => $u->phone ?? '—',
                'role'           => $u->role,
                'bookings_count' => $u->bookings_count,
                'joined'         => $u->created_at->format('d M Y'),
            ]);

        return response()->json(['users' => $users]);
    }

    // ── DELETE /api/admin/users/{id} ─────────────────────────────────────────
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Cannot delete an admin account.'], 403);
        }

        $user->delete(); // bookings cascade-deleted via FK

        return response()->json(['message' => 'User deleted successfully.']);
    }

    // ── PATCH /api/admin/users/{id}/role ─────────────────────────────────────
    public function changeRole(Request $request, $id)
    {
        $request->validate(['role' => 'required|in:user,admin']);

        $user = User::findOrFail($id);
        $user->update(['role' => $request->role]);

        return response()->json(['message' => "User role updated to {$request->role}.", 'user' => $user]);
    }
}