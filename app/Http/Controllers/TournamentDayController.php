<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentDay;
use App\Models\TournamentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentDayController extends Controller
{
    private function syncDayAssignedCourts(TournamentDay $day): void
    {
        $assignedCourts = is_array($day->assigned_courts) ? array_values($day->assigned_courts) : null;

        $day->loadMissing('subFolders', 'tournaments');

        foreach ($day->subFolders as $subFolder) {
            $subFolderCourts = is_array($subFolder->assigned_courts) ? array_values($subFolder->assigned_courts) : null;

            if ($assignedCourts === null) {
                $nextSubFolderCourts = $subFolderCourts;
            } elseif ($subFolderCourts === null) {
                $nextSubFolderCourts = null;
            } else {
                $nextSubFolderCourts = array_values(array_intersect($subFolderCourts, $assignedCourts));
            }

            $subFolder->update(['assigned_courts' => $nextSubFolderCourts]);
            $subFolder->tournaments()->update(['assigned_courts' => $nextSubFolderCourts]);
            Tournament::assignCourtsDynamically($subFolder->id);
        }

        foreach ($day->tournaments as $tournament) {
            if ($tournament->tournament_sub_folder_id) {
                continue;
            }

            $tournament->update(['assigned_courts' => $assignedCourts]);
            Tournament::assignCourtsDynamicallyForTournament($tournament->id);
        }
    }

    private function activeVenueId(): ?int
    {
        $user = auth()->user();
        if (!$user || $user->isAdmin()) {
            return null;
        }

        return $user->currentVenue()?->id;
    }

    private function scopeVenue($query)
    {
        $user = auth()->user();
        if ($user && !$user->isAdmin()) {
            $venueId = $this->activeVenueId();
            if ($venueId !== null) {
                $query->where('venue_id', $venueId);
            }
        }

        return $query;
    }

    private function ensureVenueAccess($record): void
    {
        $user = auth()->user();
        if (!$user || $user->isAdmin()) {
            return;
        }

        $venueId = $this->activeVenueId();
        if (!$venueId || (int) ($record->venue_id ?? 0) !== (int) $venueId) {
            abort(403, 'Access denied.');
        }
    }

    private function ensurePlayerOwnedDayAccess(TournamentDay $day): void
    {
        $user = auth()->user();
        if (!$user || !$user->isPlayer()) {
            abort(403, 'Only player accounts can manage this player workspace.');
        }

        $ownsApprovedDay = TournamentRequest::query()
            ->where('user_id', $user->id)
            ->where('tournament_day_id', $day->id)
            ->where('status', 'approved')
            ->exists();

        $ownsManagedTournament = $day->tournaments()->where('manager_user_id', $user->id)->exists();

        if (!$ownsApprovedDay && !$ownsManagedTournament) {
            abort(403, 'You do not own this tournament workspace.');
        }

        $hasForeignTournament = $day->tournaments()
            ->where(function ($query) use ($user) {
                $query->whereNull('manager_user_id')
                    ->orWhere('manager_user_id', '!=', $user->id);
            })
            ->exists();

        if ($hasForeignTournament) {
            abort(403, 'This folder contains tournaments outside your player workspace.');
        }
    }

    private function ensureDayManagementAccess(TournamentDay $day): void
    {
        $user = auth()->user();
        if (!$user || $user->isAdmin() || $user->isScheduler() || $user->isScorer()) {
            $this->ensureVenueAccess($day);
            return;
        }

        if ($user->isPlayer()) {
            $this->ensurePlayerOwnedDayAccess($day);
            return;
        }

        abort(403, 'This account cannot manage tournament day workspaces.');
    }

    public function index()
    {
        return response()->json([
            'data' => $this->scopeVenue(TournamentDay::withCount('tournaments')->with('venue:id,name'))->orderByDesc('date')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date_format:Y-m-d',
            'status' => 'sometimes|string|in:active,finished,archived',
            'assigned_courts' => 'nullable|array',
            'assigned_courts.*' => 'integer|min:1',
        ]);

        $validated['venue_id'] = $this->activeVenueId();
        $day = TournamentDay::create($validated);

        return back()->with('success', 'Tournament day created.');
    }

    public function update(Request $request, TournamentDay $day)
    {
        $this->ensureDayManagementAccess($day);
        $user = auth()->user();
        $validated = $request->validate(
            $user && $user->isPlayer()
                ? [
                    'name' => 'sometimes|required|string|max:255',
                    'date' => 'sometimes|required|date_format:Y-m-d',
                ]
                : [
                    'name' => 'sometimes|required|string|max:255',
                    'date' => 'sometimes|required|date_format:Y-m-d',
                    'status' => 'sometimes|string|in:active,finished,archived',
                    'assigned_courts' => 'nullable|array',
                    'assigned_courts.*' => 'integer|min:1',
                ]
        );

        if ($user && $user->isPlayer() && $day->status === 'finished') {
            abort(403, 'This tournament workspace is now view-only.');
        }

        $day->update($validated);

        if (isset($validated['status']) && (!$user || !$user->isPlayer())) {
            $archivedAt = $validated['status'] === 'archived' ? now() : null;
            $day->tournaments()->update(['archived_at' => $archivedAt]);
        }

        if (array_key_exists('assigned_courts', $validated) && (!$user || !$user->isPlayer())) {
            $this->syncDayAssignedCourts($day->fresh());
        }

        return back()->with('success', 'Tournament day updated.');
    }

    public function destroy(TournamentDay $day)
    {
        $this->ensureDayManagementAccess($day);
        $user = auth()->user();
        if ($user && $user->isPlayer() && $day->status === 'finished') {
            abort(403, 'This tournament workspace is now view-only.');
        }
        $count = $day->tournaments()->count();

        $day->delete();

        return back()->with('success', "Tournament day deleted · {$count} tournament(s) moved to Unscheduled.");
    }

    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'tournament_ids'    => 'required|array|min:1',
            'tournament_ids.*'  => 'integer|exists:tournaments,id',
            'tournament_day_id' => 'nullable|exists:tournament_days,id',
        ]);

        if (!empty($validated['tournament_day_id'])) {
            $day = TournamentDay::find($validated['tournament_day_id']);
            if ($day) {
                $this->ensureVenueAccess($day);
            }
        }

        $count = DB::transaction(function () use ($validated) {
            $venueId = $this->activeVenueId();
            $day = !empty($validated['tournament_day_id']) ? TournamentDay::find($validated['tournament_day_id']) : null;
            $count = $this->scopeVenue(Tournament::whereIn('id', $validated['tournament_ids']))->update([
                'tournament_day_id' => $validated['tournament_day_id'] ?? null,
                'venue_id' => $day?->venue_id ?? $venueId,
                'assigned_courts' => $day?->assigned_courts,
                'updated_at'        => now(),
            ]);
            return $count;
        });

        return back()->with('success', "{$count} tournament(s) updated.");
    }

    public function finishForPlayer(TournamentDay $day)
    {
        $this->ensurePlayerOwnedDayAccess($day);

        $day->update(['status' => 'finished']);

        return back()->with('success', 'Tournament day finished. This player workspace is now view-only until the scheduler approves another edit access request.');
    }
}
