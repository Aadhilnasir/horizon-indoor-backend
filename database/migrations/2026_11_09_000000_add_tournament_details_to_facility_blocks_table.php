<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_blocks', function (Blueprint $table) {
            $table->string('tournament_title', 255)->nullable();
            $table->string('sponsor', 255)->nullable();
            $table->string('organizer', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('facility_blocks', function (Blueprint $table) {
            $table->dropColumn(['tournament_title', 'sponsor', 'organizer']);
        });
    }
};
