<?php
// app/Models/Facility.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Facility extends Model
{
    protected $fillable = [
        'name', 'icon', 'tag',
        'day_rate', 'night_rate',
        'weekend_day_rate', 'weekend_night_rate',
        'holiday_day_rate', 'holiday_night_rate',
        'is_active', 'linked_facility_ids',
    ];

    protected $casts = [
        'linked_facility_ids' => 'array',
        'is_active'           => 'boolean',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    // Get the linked Facility models (e.g. Badminton 1 & 2 for Volleyball)
    public function linkedFacilities()
    {
        if (empty($this->linked_facility_ids)) {
            return collect();
        }
        return Facility::whereIn('id', $this->linked_facility_ids)->get();
    }
}