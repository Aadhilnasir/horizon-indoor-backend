<?php
// app/Models/Booking.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'user_id', 'facility_id',
        'date', 'session', 'slots',
        'total_price', 'paid_amount', 'balance_due', 'payment_status',
        'status', 'parent_booking_id', 'is_shadow',
        'guest_name', 'guest_phone', 'is_hold',
    ];

    protected $casts = [
        'slots'     => 'array',
        'date'      => 'date',
        'is_shadow' => 'boolean',
        'is_hold'   => 'boolean',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    public function parentBooking()
    {
        return $this->belongsTo(Booking::class, 'parent_booking_id');
    }

    public function shadowBookings()
    {
        return $this->hasMany(Booking::class, 'parent_booking_id');
    }
}