<?php
// app/Http/Controllers/FacilityBlockController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FacilityBlock;
use App\Models\Facility;
use Carbon\Carbon;

class FacilityBlockController extends Controller
{
    // ── GET /api/admin/facility-blocks ───────────────────────────────────────
    public function index()
    {
        $blocks = FacilityBlock::with('facility')
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(fn($b) => [
                'id'               => $b->id,
                'facility_id'      => $b->facility_id,
                'facility'         => $b->facility?->name,
                'start_date'       => $b->start_date->format('Y-m-d'),
                'end_date'         => $b->end_date->format('Y-m-d'),
                'reason'           => $b->reason,
                'tournament_title' => $b->tournament_title,
                'sponsor'          => $b->sponsor,
                'organizer'        => $b->organizer,
            ]);

        return response()->json(['blocks' => $blocks]);
    }

    // ── POST /api/admin/facility-blocks ──────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'facility_id'      => 'required|integer|exists:facilities,id',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
            'reason'           => 'sometimes|string|max:200',
            'tournament_title' => 'nullable|string|max:255',
            'sponsor'          => 'nullable|string|max:255',
            'organizer'        => 'nullable|string|max:255',
        ]);

        // Get linked facilities (shared courts)
        $facility   = Facility::findOrFail($data['facility_id']);
        $forwardIds = $facility->linked_facility_ids ?? [];

        // Reverse links: facilities whose linked_facility_ids contains the
        // selected one (e.g. Badminton 1 → Volleyball). Direct links only.
        $reverseIds = Facility::all()
            ->filter(fn($f) => ! empty($f->linked_facility_ids)
                && in_array($facility->id, $f->linked_facility_ids))
            ->pluck('id')
            ->all();

        // Selected + forward links must be free; reverse-only links are extra
        $requiredIds = array_values(array_unique(array_merge([(int) $data['facility_id']], $forwardIds)));
        $extraIds    = array_values(array_diff(array_unique($reverseIds), $requiredIds));

        $reason       = $data['reason'] ?? 'Tournament';
        $isTournament = $reason === 'Tournament';

        // Shared by the selected court and its linked courts
        $attributes = [
            'start_date'       => $data['start_date'],
            'end_date'         => $data['end_date'],
            'reason'           => $reason,
            'tournament_title' => $isTournament ? ($data['tournament_title'] ?? null) : null,
            'sponsor'          => $isTournament ? ($data['sponsor'] ?? null) : null,
            'organizer'        => $isTournament ? ($data['organizer'] ?? null) : null,
        ];

        // Reject if the selected facility or any forward-linked facility is
        // already blocked on overlapping dates, before anything is created
        foreach ($requiredIds as $checkId) {
            $existing = FacilityBlock::with('facility')
                ->where('facility_id', $checkId)
                ->whereDate('start_date', '<=', $data['end_date'])
                ->whereDate('end_date', '>=', $data['start_date'])
                ->orderBy('start_date')
                ->orderBy('id')
                ->first();

            if ($existing) {
                $name = $existing->facility?->name ?? 'This facility';

                return response()->json([
                    'message' => "{$name} is already blocked from "
                        . $existing->start_date->format('d M Y') . ' to '
                        . $existing->end_date->format('d M Y') . '.',
                ], 422);
            }
        }

        // Block the selected facility first (keeps it the lowest id)
        $block = FacilityBlock::create(['facility_id' => $data['facility_id']] + $attributes);

        // Auto-block forward-linked facilities (shared courts)
        foreach (array_slice($requiredIds, 1) as $linkedId) {
            FacilityBlock::create(['facility_id' => $linkedId] + $attributes);
        }

        // Auto-block reverse-linked facilities — always one row per block,
        // even if already blocked, so e.g. Volleyball stays blocked while
        // either badminton court is still blocked
        foreach ($extraIds as $extraId) {
            FacilityBlock::create(['facility_id' => $extraId] + $attributes);
        }

        return response()->json([
            'message' => 'Facility blocked successfully.',
            'block'   => $block,
        ], 201);
    }

    // ── DELETE /api/admin/facility-blocks/{id} ────────────────────────────────
    public function destroy($id)
    {
        $block = FacilityBlock::findOrFail($id);

        // Get forward + reverse linked facilities to unblock them too
        $facility   = Facility::findOrFail($block->facility_id);
        $forwardIds = $facility->linked_facility_ids ?? [];
        $reverseIds = Facility::all()
            ->filter(fn($f) => ! empty($f->linked_facility_ids)
                && in_array($facility->id, $f->linked_facility_ids))
            ->pluck('id')
            ->all();

        $linkedIds = array_values(array_diff(
            array_unique(array_merge($forwardIds, $reverseIds)),
            [$block->facility_id]
        ));

        // Delete at most one linked row per facility: same dates + reason,
        // the lowest id created after this block
        foreach ($linkedIds as $linkedId) {
            FacilityBlock::where('facility_id', $linkedId)
                ->whereDate('start_date', $block->start_date)
                ->whereDate('end_date', $block->end_date)
                ->where('reason', $block->reason)
                ->where('id', '>', $block->id)
                ->orderBy('id')
                ->first()
                ?->delete();
        }

        $block->delete();

        return response()->json(['message' => 'Block removed successfully.']);
    }

    // ── GET /api/facility-blocks/upcoming ────────────────────────────────────
    // Public — blocks ending today or later
    public function upcoming()
    {
        $blocks = FacilityBlock::with('facility')
            ->whereDate('end_date', '>=', today())
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn($b) => [
                'id'         => $b->id,
                'facility_id' => $b->facility_id,
                'facility'   => $b->facility?->name,
                'start_date' => $b->start_date->format('Y-m-d'),
                'end_date'   => $b->end_date->format('Y-m-d'),
                'reason'     => $b->reason,
            ]);

        return response()->json(['blocks' => $blocks]);
    }

    // ── GET /api/facility-blocks?facility_id=&date= ──────────────────────────
    // Public — check if a facility is blocked on a date
    public function check(Request $request)
    {
        $request->validate([
            'facility_id' => 'required|integer',
            'date'        => 'required|date',
        ]);

        $blocked = FacilityBlock::where('facility_id', $request->facility_id)
            ->where('start_date', '<=', $request->date)
            ->where('end_date',   '>=', $request->date)
            ->first();

        return response()->json([
            'blocked' => (bool) $blocked,
            'reason'  => $blocked?->reason,
        ]);
    }
}