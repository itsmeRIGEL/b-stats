<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentSubFolder;
use App\Models\TournamentDay;
use App\Models\TournamentRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TournamentSubFolderController extends Controller
{
    private function normalizeAllowedDayCourts(TournamentDay $day): ?array
    {
        if (!is_array($day->assigned_courts) || empty($day->assigned_courts)) {
            return null;
        }

        return array_values(array_unique(array_map('intval', $day->assigned_courts)));
    }

    private function validateAssignedCourtsAgainstDay(array $validated, TournamentDay $day): void
    {
        if (!array_key_exists('assigned_courts', $validated) || !is_array($validated['assigned_courts'])) {
            return;
        }

        $allowedCourts = $this->normalizeAllowedDayCourts($day);
        if ($allowedCourts === null) {
            return;
        }

        $selectedCourts = array_values(array_unique(array_map('intval', $validated['assigned_courts'])));
        $invalidCourts = array_values(array_diff($selectedCourts, $allowedCourts));

        if (!empty($invalidCourts)) {
            throw ValidationException::withMessages([
                'assigned_courts' => 'Sub-folder courts must be selected from the scheduler-approved courts in the main folder.',
            ]);
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

        if ($user->isPlayer()) {
            $dayId = $record->tournament_day_id ?? null;
            $ownsApprovedDay = $dayId
                ? TournamentRequest::query()
                    ->where('user_id', $user->id)
                    ->where('tournament_day_id', $dayId)
                    ->where('status', 'approved')
                    ->exists()
                : false;

            if ($ownsApprovedDay) {
                return;
            }

            abort(403, 'Access denied. Your request for this tournament workspace is not approved.');
        }

        $venueId = $this->activeVenueId();
        if (!$venueId || (int) ($record->venue_id ?? 0) !== (int) $venueId) {
            abort(403, 'Access denied.');
        }
    }

    private function ensureAccessibleTournamentDayId(?int $dayId): void
    {
        if (!$dayId) {
            return;
        }

        $day = TournamentDay::find($dayId);
        if ($day) {
            $user = auth()->user();
            if ($user && $user->isPlayer()) {
                $ownsApprovedDay = TournamentRequest::query()
                    ->where('user_id', $user->id)
                    ->where('tournament_day_id', $day->id)
                    ->where('status', 'approved')
                    ->exists();

                $hasForeignTournament = $day->tournaments()
                    ->where(function ($query) use ($user) {
                        $query->whereNull('manager_user_id')
                            ->orWhere('manager_user_id', '!=', $user->id);
                    })
                    ->exists();

                if (!$ownsApprovedDay || $hasForeignTournament) {
                    abort(403, 'Access denied.');
                }

                if (in_array($day->status, ['finished', 'archived'], true)) {
                    abort(403, 'This tournament workspace is now view-only.');
                }

                return;
            }

            $this->ensureVenueAccess($day);
        }
    }

    private function ensureAccessibleAssignedScorerId(?int $scorerId): void
    {
        if (!$scorerId) {
            return;
        }

        $user = auth()->user();
        $query = User::query()
            ->whereIn('role', ['scorer', 'scheduler_scorer'])
            ->whereKey($scorerId);

        if ($user && !$user->isAdmin()) {
            $query->where('scheduler_id', $user->id);
        }

        if (!$query->exists()) {
            throw ValidationException::withMessages([
                'assigned_scorer_id' => 'You can only assign scorers created under your account.',
            ]);
        }
    }

    public function index(Request $request)
    {
        $query = TournamentSubFolder::withCount('tournaments')->orderBy('order')->orderBy('name');

        $user = auth()->user();
        if ($user && $user->isPlayer()) {
            $approvedDayIds = TournamentRequest::query()
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereNotNull('tournament_day_id')
                ->pluck('tournament_day_id');

            $query->whereIn('tournament_day_id', $approvedDayIds);
        } else {
            $query = $this->scopeVenue($query);
        }

        if ($request->filled('tournament_day_id')) {
            $query->where('tournament_day_id', $request->integer('tournament_day_id'));
        }
        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tournament_day_id' => 'required|exists:tournament_days,id',
            'order' => 'nullable|integer|min:0',
            'assigned_scorer_id' => 'nullable|exists:users,id',
            'assigned_courts' => 'nullable|array',
            'assigned_courts.*' => 'integer|min:1',
        ]);

        $this->ensureAccessibleTournamentDayId((int) $validated['tournament_day_id']);
        $this->ensureAccessibleAssignedScorerId(isset($validated['assigned_scorer_id']) ? (int) $validated['assigned_scorer_id'] : null);

        $day = TournamentDay::findOrFail((int) $validated['tournament_day_id']);
        $this->validateAssignedCourtsAgainstDay($validated, $day);
        $validated['order'] = $validated['order'] ?? 0;
        $validated['venue_id'] = $day->venue_id;
        $validated['assigned_courts'] = array_key_exists('assigned_courts', $validated)
            ? $validated['assigned_courts']
            : null;

        $subFolder = TournamentSubFolder::create($validated);

        return back()->with('success', 'Sub-folder created.')->with('new_sub_folder_id', $subFolder->id);
    }

    public function update(Request $request, TournamentSubFolder $subFolder)
    {
        $this->ensureVenueAccess($subFolder);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'order' => 'sometimes|integer|min:0',
            'assigned_scorer_id' => 'nullable|exists:users,id',
            'start_time' => 'sometimes|required|string',
            'match_duration' => 'sometimes|required|integer|min:1',
            'rest_time' => 'sometimes|required|integer|min:0',
            'enable_break' => 'sometimes|required|boolean',
            'break_start' => 'nullable|required_if:enable_break,true|string',
            'break_end' => 'nullable|required_if:enable_break,true|string',
            'assigned_courts' => 'nullable|array',
            'assigned_courts.*' => 'integer|min:1',
        ]);

        if (array_key_exists('assigned_scorer_id', $validated)) {
            $this->ensureAccessibleAssignedScorerId($validated['assigned_scorer_id'] ? (int) $validated['assigned_scorer_id'] : null);
        }

        if ($request->input('enable_break')) {
            $toMins = function($t) {
                if (!$t) return 0;
                $parts = explode(':', $t);
                return ((int)$parts[0]) * 60 + ((int)($parts[1] ?? 0));
            };
            if ($toMins($request->input('break_end')) <= $toMins($request->input('break_start'))) {
                return redirect()->back()->withErrors(['break_end' => 'Break End Time must be greater than Break Start Time.']);
            }
        }

        // Handle case where assigned_courts is not provided or empty
        if ($request->has('assigned_courts') && !$request->filled('assigned_courts')) {
            $validated['assigned_courts'] = null;
        }

        $day = $subFolder->tournamentDay;
        if ($day) {
            $this->validateAssignedCourtsAgainstDay($validated, $day);
        }

        $subFolder->update($validated);
        if (array_key_exists('assigned_courts', $validated)) {
            $subFolder->tournaments()->update(['assigned_courts' => $subFolder->assigned_courts]);
        }

        // Apply dynamic court assignments
        Tournament::assignCourtsDynamically($subFolder->id);

        if ($request->hasAny(['start_time', 'match_duration', 'rest_time', 'enable_break'])) {
            $scheduleData = $request->only(['start_time', 'match_duration', 'rest_time', 'enable_break', 'break_start', 'break_end']);
            $scheduleData['court_count'] = is_array($subFolder->assigned_courts) ? count($subFolder->assigned_courts) : 1;
            
            // First, update all tournaments in the subfolder
            foreach ($subFolder->tournaments as $tournament) {
                $tournament->update($request->only(['start_time', 'match_duration', 'rest_time', 'enable_break', 'break_start', 'break_end']));
            }

            // Next, group tournaments in this subfolder by type and synchronize schedules for in_progress ones
            $groups = $subFolder->tournaments()->where('status', 'in_progress')->get()->groupBy('type');
            foreach ($groups as $type => $tournamentsInGroup) {
                Tournament::generateSharedMatchSchedules($tournamentsInGroup, $scheduleData);
            }
        }

        return back()->with('success', 'Sub-folder updated.');
    }

    public function destroy(TournamentSubFolder $subFolder)
    {
        $this->ensureVenueAccess($subFolder);
        $count = $subFolder->tournaments()->count();
        $subFolder->delete();

        return back()->with('success', "Sub-folder deleted · {$count} tournament(s) moved to unfiled.");
    }

    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'tournament_ids' => 'required|array|min:1',
            'tournament_ids.*' => 'integer|exists:tournaments,id',
            'tournament_sub_folder_id' => 'nullable|exists:tournament_sub_folders,id',
        ]);

        $subFolderId = $validated['tournament_sub_folder_id'] ?? null;
        $resolvedDayId = null;
        $subFolder = null;

        if ($subFolderId !== null) {
            $subFolder = TournamentSubFolder::findOrFail($subFolderId);
            $this->ensureVenueAccess($subFolder);
            $this->ensureAccessibleTournamentDayId((int) $subFolder->tournament_day_id);
            $resolvedDayId = $subFolder->tournament_day_id;
        }

        $count = DB::transaction(function () use ($validated, $subFolderId, $resolvedDayId, $subFolder) {
            $user = auth()->user();
            $updateData = [
                'tournament_sub_folder_id' => $subFolderId,
                'updated_at' => now(),
            ];

            if (!$user || !$user->isPlayer()) {
                $updateData['venue_id'] = $this->activeVenueId();
            }

            if ($resolvedDayId !== null) {
                $updateData['tournament_day_id'] = $resolvedDayId;
            }

            if ($subFolder) {
                $updateData['start_time'] = $subFolder->start_time;
                $updateData['match_duration'] = $subFolder->match_duration;
                $updateData['rest_time'] = $subFolder->rest_time;
                $updateData['enable_break'] = $subFolder->enable_break;
                $updateData['break_start'] = $subFolder->break_start;
                $updateData['break_end'] = $subFolder->break_end;
                $updateData['assigned_courts'] = $subFolder->assigned_courts;
            }

            $affectedCount = 0;
            $tournamentQuery = Tournament::whereIn('id', $validated['tournament_ids']);

            if ($user && $user->isPlayer()) {
                $tournamentQuery->where('manager_user_id', $user->id);
            } else {
                $tournamentQuery = $this->scopeVenue($tournamentQuery);
            }

            $tournaments = $tournamentQuery->get();
            foreach ($tournaments as $tournament) {
                $tournament->update($updateData);
                $affectedCount++;
            }

            if ($subFolder) {
                // Clear any manual court assignments for matches that don't have a score yet
                foreach ($tournaments as $tournament) {
                    $tournament->matches()
                        ->whereNull('winner_id')
                        ->whereNull('team1_score')
                        ->whereNull('team2_score')
                        ->update(['court_number' => null]);
                }

                // Synchronize schedules in subfolder
                $groups = $subFolder->tournaments()->where('status', 'in_progress')->get()->groupBy('type');
                foreach ($groups as $type => $tournamentsInGroup) {
                    Tournament::generateSharedMatchSchedules($tournamentsInGroup, [
                        'start_time' => $subFolder->start_time,
                        'match_duration' => $subFolder->match_duration,
                        'rest_time' => $subFolder->rest_time,
                        'enable_break' => $subFolder->enable_break,
                        'break_start' => $subFolder->break_start,
                        'break_end' => $subFolder->break_end,
                    ]);
                }
                Tournament::assignCourtsDynamically($subFolder->id);
            } else {
                // Clear court numbers and regenerate schedule times individually since they are moved out of a subfolder
                foreach ($tournaments as $tournament) {
                    $tournament->matches()->update(['court_number' => null]);
                    if ($tournament->status === 'in_progress') {
                        $tournament->generateMatchSchedules();
                    }
                }
            }

            return $affectedCount;
        });

        return back()->with('success', "{$count} tournament(s) updated.");
    }
}
