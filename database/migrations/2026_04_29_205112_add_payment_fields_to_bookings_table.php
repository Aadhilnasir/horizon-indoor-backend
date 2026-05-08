<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->integer('paid_amount')->default(0)->after('total_price');
            $table->integer('balance_due')->default(0)->after('paid_amount');
            $table->string('payment_status')->default('pending')->after('balance_due');
            // pending = nothing paid, partial = deposit paid, paid = full amount paid
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'balance_due', 'payment_status']);
        });
    }
};