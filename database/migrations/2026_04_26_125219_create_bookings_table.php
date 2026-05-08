<?php
// database/migrations/2024_01_01_000003_create_bookings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();

            $table->date('date');
            $table->enum('session', ['day', 'night']);

            // Store slots as JSON array e.g. ["08:00 – 09:00", "09:00 – 10:00"]
            $table->json('slots');

            $table->integer('total_price');             // LKR total

            $table->enum('status', ['confirmed', 'cancelled'])->default('confirmed');

            // ── LINKED BOOKING ──────────────────────────────────────────────
            // When Volleyball is booked, we auto-create shadow bookings for
            // Badminton 1 & 2. Those shadow bookings store the parent booking id
            // so we know they were auto-created and can be auto-cancelled together.
            $table->foreignId('parent_booking_id')
                  ->nullable()
                  ->constrained('bookings')
                  ->nullOnDelete();

            $table->boolean('is_shadow')->default(false); // true = auto-created linked booking

            $table->timestamps();

            // Prevent double-booking: same facility, date, session cannot have overlapping slots
            // (enforced in application logic, not DB unique — slots are JSON)
            $table->index(['facility_id', 'date', 'session']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};