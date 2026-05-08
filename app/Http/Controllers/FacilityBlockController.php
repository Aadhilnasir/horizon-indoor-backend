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
                'id'          => $b->id,
                'facility_id' => $b->facility_id,
                'facility'    => $b->facility?->name,
                'start_date'  => $b->start_date->format('Y-m-d'),
                'end_date'    => $b->end_date->format('Y-m-d'),
                'reason'      => $b->reason,
            ]);

        return response()->json(['blocks' => $blocks]);
    }

    // ── POST /api/admin/facility-blocks ──────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'facility_id' => 'required|integer|exists:facilities,id',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'reason'      => 'sometimes|string|max:200',
        ]);

        // Get linked facilities (shared courts)
        $facility = Facility::findOrFail($data['facility_id']);
        $linkedIds = $facility->linked_facility_ids ?? [];

        // Block the selected facility
        $block = FacilityBlock::create([
            'facility_id' => $data['facility_id'],
            'start_date'  => $data['start_date'],
            'end_date'    => $data['end_date'],
            'reason'      => $data['reason'] ?? 'Tournament',
        ]);

        // Auto-block linked facilities (shared courts)
        foreach ($linkedIds as $linkedId) {
            FacilityBlock::create([
                'facility_id' => $linkedId,
                'start_date'  => $data['start_date'],
                'end_date'    => $data['end_date'],
                'reason'      => $data['reason'] ?? 'Tournament',
            ]);
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

        // Get linked facilities to unblock them too
        $facility  = Facility::findOrFail($block->facility_id);
        $linkedIds = $facility->linked_facility_ids ?? [];

        // Delete linked blocks with same dates
        foreach ($linkedIds as $linkedId) {
            FacilityBlock::where('facility_id', $linkedId)
                ->where('start_date', $block->start_date)
                ->where('end_date', $block->end_date)
                ->delete();
        }

        $block->delete();

        return response()->json(['message' => 'Block removed successfully.']);
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