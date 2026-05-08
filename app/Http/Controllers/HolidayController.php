<?php
// app/Http/Controllers/HolidayController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Holiday;

class HolidayController extends Controller
{
    // ── GET /api/holidays?year=2026 ──────────────────────────────────────────
    public function index(Request $request)
    {
        $year = (int) $request->get('year', date('Y'));

        // Auto-generate if no holidays exist for this year
        if (Holiday::whereYear('date', $year)->count() === 0) {
            $this->autoGenerateForYear($year);
        }

        $holidays = Holiday::whereYear('date', $year)
            ->orderBy('date')
            ->get()
            ->map(fn($h) => [
                'date' => $h->date->format('Y-m-d'),
                'name' => $h->name,
            ]);

        return response()->json(['holidays' => $holidays]);
    }

    // ── GET /api/admin/holidays ──────────────────────────────────────────────
    public function adminIndex()
    {
        $holidays = Holiday::where('date', '>=', today())
            ->orderBy('date')
            ->get()
            ->map(fn($h) => [
                'id'   => $h->id,
                'date' => $h->date->format('Y-m-d'),
                'name' => $h->name,
                'year' => $h->date->format('Y'),
                'type' => $h->type ?? 'manual',
            ]);

        return response()->json(['holidays' => $holidays]);
    }

    // ── POST /api/admin/holidays/generate/{year} ─────────────────────────────
    public function generate(int $year)
    {
        // Don't delete existing holidays — just add missing ones
        // This preserves manually corrected dates
        $generated = $this->autoGenerateForYear($year);

        return response()->json([
            'message'   => "Generated {$generated} new holidays for {$year}. Existing holidays were kept.",
            'generated' => $generated,
        ]);
    }

    // ── POST /api/admin/holidays ─────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date|unique:holidays,date',
            'name' => 'required|string|max:200',
        ]);

        $holiday = Holiday::create([
            'date' => $data['date'],
            'name' => $data['name'],
            'type' => 'manual',
        ]);

        return response()->json([
            'message' => 'Holiday added.',
            'holiday' => ['id' => $holiday->id, 'date' => $holiday->date->format('Y-m-d'), 'name' => $holiday->name],
        ], 201);
    }

    // ── DELETE /api/admin/holidays/{id} ──────────────────────────────────────
    public function destroy(int $id)
    {
        Holiday::findOrFail($id)->delete();
        return response()->json(['message' => 'Holiday deleted.']);
    }

    // ── AUTO GENERATION ───────────────────────────────────────────────────────
    private function autoGenerateForYear(int $year): int
    {
        $created = 0;

        // Fixed holidays — same date every year
        $fixed = [
            "{$year}-01-01" => "New Year's Day",
            "{$year}-02-04" => "Independence Day",
            "{$year}-05-01" => "Labour Day",
            "{$year}-12-25" => "Christmas Day",
        ];

        foreach ($fixed as $date => $name) {
            if (!Holiday::where('date', $date)->exists()) {
                Holiday::create(['date' => $date, 'name' => $name, 'type' => 'auto']);
                $created++;
            }
        }

        // Official Poya days — hardcoded from government gazette per year
        // Moon algorithm is unreliable — use official dates only
        $poyaDays = $this->getOfficialPoyaDays($year);
        foreach ($poyaDays as $date => $name) {
            if (!Holiday::where('date', $date)->exists()) {
                Holiday::create(['date' => $date, 'name' => $name, 'type' => 'auto']);
                $created++;
            }
        }

        // Variable holidays (Eid, Good Friday etc)
        foreach ($this->getVariableHolidays($year) as $date => $name) {
            if (!Holiday::where('date', $date)->exists()) {
                Holiday::create(['date' => $date, 'name' => $name, 'type' => 'auto']);
                $created++;
            }
        }

        return $created;
    }

    /**
     * Official Poya dates from Government Gazette.
     * Add new years here every December when government announces.
     * DO NOT use moon algorithm — it gives wrong dates.
     */
    private function getOfficialPoyaDays(int $year): array
    {
        $map = [
            2026 => [
                '2026-01-03' => 'Duruthu Full Moon Poya Day',
                '2026-02-01' => 'Navam Full Moon Poya Day',
                '2026-03-02' => 'Medin Full Moon Poya Day',
                '2026-04-01' => 'Bak Full Moon Poya Day',
                '2026-05-01' => 'Vesak Full Moon Poya Day',
                '2026-05-02' => 'Day after Vesak Full Moon Poya Day',
                '2026-05-30' => 'Adhi Poson Full Moon Poya Day',
                '2026-06-29' => 'Poson Full Moon Poya Day',
                '2026-07-29' => 'Esala Full Moon Poya Day',
                '2026-08-27' => 'Nikini Full Moon Poya Day',
                '2026-09-26' => 'Binara Full Moon Poya Day',
                '2026-10-25' => 'Vap Full Moon Poya Day',
                '2026-11-24' => 'Ill Full Moon Poya Day',
                '2026-12-23' => 'Unduvap Full Moon Poya Day',
            ],
            2027 => [
                '2027-01-13' => 'Duruthu Full Moon Poya Day',
                '2027-02-12' => 'Navam Full Moon Poya Day',
                '2027-03-13' => 'Medin Full Moon Poya Day',
                '2027-04-12' => 'Bak Full Moon Poya Day',
                '2027-05-12' => 'Vesak Full Moon Poya Day',
                '2027-05-13' => 'Day after Vesak Full Moon Poya Day',
                '2027-06-10' => 'Poson Full Moon Poya Day',
                '2027-07-10' => 'Esala Full Moon Poya Day',
                '2027-08-09' => 'Nikini Full Moon Poya Day',
                '2027-09-07' => 'Binara Full Moon Poya Day',
                '2027-10-07' => 'Vap Full Moon Poya Day',
                '2027-11-05' => 'Ill Full Moon Poya Day',
                '2027-12-05' => 'Unduvap Full Moon Poya Day',
            ],
            // Add 2028 and beyond every December from government gazette
            // Admin can also add manually from Holidays tab
        ];

        return $map[$year] ?? [];
    }

    // Variable holidays that shift year to year
    // Admin adds new years manually from the panel when government announces
    private function getVariableHolidays(int $year): array
    {
        $map = [
            2025 => [
                '2025-01-14' => 'Tamil Thai Pongal Day',
                '2025-02-26' => 'Maha Sivarathri',
                '2025-03-31' => 'Id-Ul-Fitr',
                '2025-04-13' => 'Day prior to Sinhala & Tamil New Year',
                '2025-04-14' => 'Sinhala & Tamil New Year',
                '2025-04-18' => 'Good Friday',
                '2025-06-07' => 'Id-Ul-Alha',
                '2025-10-20' => 'Deepavali',
            ],
            2026 => [
                '2026-01-15' => 'Tamil Thai Pongal Day',
                '2026-02-15' => 'Maha Shivaratri Day',
                '2026-03-21' => 'Eid-ul-Fitr (Ramadan Festival Day)',
                '2026-04-03' => 'Good Friday',
                '2026-04-13' => 'Day before Sinhala & Tamil New Year',
                '2026-04-14' => 'Sinhala & Tamil New Year Day',
                '2026-05-28' => 'Eid al-Adha (Hajj Festival Day)',
                '2026-08-26' => "Milad-un-Nabi (Prophet Muhammad's Birthday)",
                '2026-11-08' => 'Deepavali Festival Day',
            ],
            2027 => [
                '2027-01-14' => 'Tamil Thai Pongal Day',
                '2027-02-26' => 'Maha Shivaratri Day',
                '2027-03-31' => 'Id-Ul-Fitr (Ramadan Festival Day)',
                '2027-04-13' => 'Day prior to Sinhala & Tamil New Year',
                '2027-04-14' => 'Sinhala & Tamil New Year Day',
                '2027-04-18' => 'Good Friday',
                // Eid al-Adha, Milad-un-Nabi, Deepavali
                // Add these from admin panel when government announces
            ],
        ];

        return $map[$year] ?? [];
    }
}