<?php
// database/migrations/2024_01_01_000002_create_facilities_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->default('🏟️');
            $table->string('tag')->nullable();           // e.g. "Indoor · court"
            $table->integer('day_rate');
            $table->integer('night_rate');
            $table->integer('weekend_day_rate');
            $table->integer('weekend_night_rate');
            $table->integer('holiday_day_rate');
            $table->integer('holiday_night_rate');
            $table->boolean('is_active')->default(true);

            // ── SHARED COURT LOGIC ──────────────────────────────────────────
            // If a facility is "shared", booking it auto-books its linked facilities.
            // Example: Volleyball Court → links to Badminton Court 1 & 2
            // We store the IDs of facilities that get auto-blocked as JSON.
            // Badminton Court 1 & 2 are NOT shared (booking one doesn't block the other).
            $table->json('linked_facility_ids')->nullable();
            // e.g. for Volleyball: [3, 4]  (Badminton 1 id=3, Badminton 2 id=4)
            // e.g. for Badminton 1 & 2: null (independent)

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};