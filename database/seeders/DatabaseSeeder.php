<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Facility;
use App\Models\Holiday;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin user ───────────────────────────────────────────────────────
        User::create([
            'first_name' => 'Mohammed',
            'last_name'  => 'Nasreen',
            'username'   => 'admin@horizon',
            'email'      => 'nasreen2717@gmail.com',
            'password'   => Hash::make('nasreenslk5050'),
            'role'       => 'admin',
        ]);

        // ── Facilities ───────────────────────────────────────────────────────
        //
        // Rates (LKR per hour):
        //   day_rate            → normal weekday day session
        //   night_rate          → normal weekday night session
        //   weekend_day_rate    → Saturday/Sunday day session
        //   weekend_night_rate  → Saturday/Sunday night session
        //   holiday_day_rate    → public holiday day session
        //   holiday_night_rate  → public holiday night session
        //
        // To change prices: edit below and run php artisan migrate:fresh --seed

        // ── Place 1: Football & Cricket 1 (same ground) ──────────────────────
        $football = Facility::create([
            'name'                => 'Football',
            'icon'                => '⚽',
            'tag'                 => 'Indoor · Full pitch',
            'day_rate'            => 2000,
            'night_rate'          => 1500,
            'weekend_day_rate'    => 3000,
            'weekend_night_rate'  => 2500,
            'holiday_day_rate'    => 3000,
            'holiday_night_rate'  => 2500,
            'linked_facility_ids' => null,
        ]);

        $cricket1 = Facility::create([
            'name'                => 'Cricket 1',
            'icon'                => '🏏',
            'tag'                 => 'Indoor · Full pitch',
            'day_rate'            => 1500,
            'night_rate'          => 2500,
            'weekend_day_rate'    => 2000,
            'weekend_night_rate'  => 2500,
            'holiday_day_rate'    => 2000,
            'holiday_night_rate'  => 2500,
            'linked_facility_ids' => [$football->id],
        ]);

        $football->update(['linked_facility_ids' => [$cricket1->id]]);

        // ── Place 2: Cricket Practice Net & Cricket 2 (same area) ────────────
        $cricketNet = Facility::create([
            'name'                => 'Cricket Practice Net',
            'icon'                => '🏏',
            'tag'                 => 'Indoor · Practice net',
            'day_rate'            => 800,
            'night_rate'          => 800,
            'weekend_day_rate'    => 800,
            'weekend_night_rate'  => 800,
            'holiday_day_rate'    => 800,
            'holiday_night_rate'  => 800,
            'linked_facility_ids' => null,
        ]);

        $cricket2 = Facility::create([
            'name'                => 'Cricket 2',
            'icon'                => '🏏',
            'tag'                 => 'Indoor · Practice net',
            'day_rate'            => 1200,
            'night_rate'          => 1500,
            'weekend_day_rate'    => 1500,
            'weekend_night_rate'  => 1500,
            'holiday_day_rate'    => 1500,
            'holiday_night_rate'  => 1500,
            'linked_facility_ids' => [$cricketNet->id],
        ]);

        $cricketNet->update(['linked_facility_ids' => [$cricket2->id]]);

        // ── Place 3: Badminton 1, Badminton 2 & Volleyball (same area) ───────
        $badminton1 = Facility::create([
            'name'                => 'Badminton Court 1',
            'icon'                => '🏸',
            'tag'                 => 'Indoor · court',
            'day_rate'            => 500,
            'night_rate'          => 600,
            'weekend_day_rate'    => 600,
            'weekend_night_rate'  => 600,
            'holiday_day_rate'    => 600,
            'holiday_night_rate'  => 600,
            'linked_facility_ids' => null,
        ]);

        $badminton2 = Facility::create([
            'name'                => 'Badminton Court 2',
            'icon'                => '🏸',
            'tag'                 => 'Indoor · court',
            'day_rate'            => 500,
            'night_rate'          => 600,
            'weekend_day_rate'    => 600,
            'weekend_night_rate'  => 600,
            'holiday_day_rate'    => 600,
            'holiday_night_rate'  => 600,
            'linked_facility_ids' => null,
        ]);

        Facility::create([
            'name'                => 'Volleyball Court',
            'icon'                => '🏐',
            'tag'                 => 'Indoor · court',
            'day_rate'            => 1500,
            'night_rate'          => 2000,
            'weekend_day_rate'    => 1500,
            'weekend_night_rate'  => 2000,
            'holiday_day_rate'    => 1500,
            'holiday_night_rate'  => 2000,
            'linked_facility_ids' => [$badminton1->id, $badminton2->id],
        ]);

        // ── Place 4: Pool Table (independent) ────────────────────────────────
        Facility::create([
            'name'                => 'Pool Table',
            'icon'                => '🎱',
            'tag'                 => 'Indoor · table',
            'day_rate'            => 500,
            'night_rate'          => 500,
            'weekend_day_rate'    => 500,
            'weekend_night_rate'  => 500,
            'holiday_day_rate'    => 500,
            'holiday_night_rate'  => 500,
            'linked_facility_ids' => null,
        ]);

        // ── 2026 Holidays (seed initial set) ─────────────────────────────────
        // Admin can add more years from the Admin Panel → Holidays tab
        // ✅ Official 2026 Sri Lanka Public Holidays - 26 days
        // Source: Government Printing Department (Ada Derana, December 2025)
        $holidays = [
            ['date' => '2026-01-01', 'name' => "New Year's Day",                          'type' => 'auto'],
            ['date' => '2026-01-03', 'name' => 'Duruthu Full Moon Poya Day',               'type' => 'auto'],
            ['date' => '2026-01-15', 'name' => 'Tamil Thai Pongal Day',                    'type' => 'manual'],
            ['date' => '2026-02-01', 'name' => 'Navam Full Moon Poya Day',                 'type' => 'auto'],
            ['date' => '2026-02-04', 'name' => 'Independence Day',                         'type' => 'auto'],
            ['date' => '2026-02-15', 'name' => 'Maha Shivaratri Day',                      'type' => 'manual'],
            ['date' => '2026-03-02', 'name' => 'Medin Full Moon Poya Day',                 'type' => 'auto'],
            ['date' => '2026-03-21', 'name' => 'Eid-ul-Fitr (Ramadan Festival Day)',       'type' => 'manual'],
            ['date' => '2026-04-01', 'name' => 'Bak Full Moon Poya Day',                   'type' => 'auto'],
            ['date' => '2026-04-03', 'name' => 'Good Friday',                              'type' => 'manual'],
            ['date' => '2026-04-13', 'name' => 'Day before Sinhala & Tamil New Year',      'type' => 'auto'],
            ['date' => '2026-04-14', 'name' => 'Sinhala & Tamil New Year Day',             'type' => 'auto'],
            ['date' => '2026-05-01', 'name' => 'Vesak Full Moon Poya Day / Labour Day',    'type' => 'auto'],
            ['date' => '2026-05-02', 'name' => 'Day after Vesak Full Moon Poya Day',       'type' => 'auto'],
            ['date' => '2026-05-28', 'name' => 'Eid al-Adha (Hajj Festival Day)',          'type' => 'manual'],
            ['date' => '2026-05-30', 'name' => 'Adhi Poson Full Moon Poya Day',            'type' => 'auto'],
            ['date' => '2026-06-29', 'name' => 'Poson Full Moon Poya Day',                 'type' => 'auto'],
            ['date' => '2026-07-29', 'name' => 'Esala Full Moon Poya Day',                 'type' => 'auto'],
            ['date' => '2026-08-26', 'name' => "Milad-un-Nabi (Prophet Muhammad's Birthday)", 'type' => 'manual'],
            ['date' => '2026-08-27', 'name' => 'Nikini Full Moon Poya Day',                'type' => 'auto'],
            ['date' => '2026-09-26', 'name' => 'Binara Full Moon Poya Day',                'type' => 'auto'],
            ['date' => '2026-10-25', 'name' => 'Vap Full Moon Poya Day',                   'type' => 'auto'],
            ['date' => '2026-11-08', 'name' => 'Deepavali Festival Day',                   'type' => 'manual'],
            ['date' => '2026-11-24', 'name' => 'Ill Full Moon Poya Day',                   'type' => 'auto'],
            ['date' => '2026-12-23', 'name' => 'Unduvap Full Moon Poya Day',               'type' => 'auto'],
            ['date' => '2026-12-25', 'name' => 'Christmas Day',                            'type' => 'auto'],
        ];

        foreach ($holidays as $h) {
            Holiday::create($h);
        }
    }
}