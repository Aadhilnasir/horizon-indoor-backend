<?php
// app/Models/SlotLock.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlotLock extends Model
{
    protected $fillable = [
        'user_id', 'facility_id', 'date', 'session', 'slot', 'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    // ── Relation — needed for admin lock check in BookingController ──────────
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}