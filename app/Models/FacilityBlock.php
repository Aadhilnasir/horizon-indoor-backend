<?php
// app/Models/FacilityBlock.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacilityBlock extends Model
{
    protected $fillable = [
        'facility_id', 'start_date', 'end_date', 'reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }
}