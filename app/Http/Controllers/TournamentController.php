<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentDay;
use App\Models\TournamentSubFolder;
use App\Models\TournamentMatch;
use App\Models\TournamentPlayer;
use App\Models\TournamentRequest;
use App\Models\Player;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TournamentController extends Controller
{
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
            $table = method_exists($query, 'getModel') ? $query->getModel()->getTable() : null;
            if ($venueId !== null && $table && Schema::hasTable($table) && Schema::hasColumn($table, 'venue_id')) {
                $query->where('venue_id', $venueId);
            }
        }

        return $query;
    }

    private function scopeTournaments($query)
    {
        $user = auth()->user();

        if ($user && $user->isPlayer()) {
            return $query->where('manager_user_id', $user->id);
        }

        return $this->scopeVenue($query);
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

    private function ensureTournamentAccess(Tournament $tournament): void
    {
        $user = auth()->user();
        if (!$user || $user->isAdmin()) {
            return;
        }

        if ($user->isPlayer() && (int) $tournament->manager_user_id === (int) $user->id) {
            return;
        }

        $this->ensureVenueAccess($tournament);
    }

    private function ensureTournamentEditable(Tournament $tournament): void
    {
        $user = auth()->user();
        if (!$user || $user->isAdmin() || $user->isScheduler() || $user->isScorer()) {
            return;
        }

        if (!$user->isPlayer()) {
            return;
        }

        if ((int) $tournament->manager_user_id !== (int) $user->id) {
            abort(403, 'Access denied.');
        }

        $day = $tournament->tournamentDay;
        if ($day && in_array($day->status, ['finished', 'archived'], true)) {
            abort(403, 'This tournament workspace is now view-only. Request edit access from the scheduler to make changes again.');
        }
    }

    private function availableScorersQuery()
    {
        $user = auth()->user();

        $query = User::query()
            ->whereIn('role', ['scorer', 'scheduler_scorer'])
            ->select('id', 'name');

        if (!$user || $user->isAdmin()) {
            return $query;
        }

        return $query->where('scheduler_id', $user->id);
    }

    private function ensureAccessibleTournamentDayId(?int $dayId): void
    {
        if (!$dayId) {
            return;
        }

        $day = TournamentDay::find($dayId);
        if (!$day) {
            return;
        }
        $user = auth()->user();
        if ($user && $user->isPlayer()) {
            $approvedAccess = TournamentRequest::query()
                ->where('user_id', $user->id)
                ->where('tournament_day_id', $day->id)
                ->whereIn('status', ['approved'])
                ->exists();

            if (!$approvedAccess) {
                abort(403, 'Access denied.');
            }

            return;
        }

        $this->ensureVenueAccess($day);
    }

    private function ensureAccessibleSubFolderId(?int $subFolderId): ?TournamentSubFolder
    {
        if (!$subFolderId) {
            return null;
        }

        $subFolder = TournamentSubFolder::find($subFolderId);
        if (!$subFolder) {
            return null;
        }

        $user = auth()->user();
        if ($user && $user->isPlayer()) {
            $ownsApprovedDay = TournamentRequest::query()
                ->where('user_id', $user->id)
                ->where('tournament_day_id', $subFolder->tournament_day_id)
                ->where('status', 'approved')
                ->exists();

            $ownsManagedTournament = $subFolder->tournaments()
                ->where('manager_user_id', $user->id)
                ->exists();

            if (!$ownsApprovedDay && !$ownsManagedTournament) {
                abort(403, 'Access denied.');
            }

            return $subFolder;
        }

        $this->ensureVenueAccess($subFolder);

        return $subFolder;
    }

    public function index()
    {
        $user = auth()->user();

        $tournaments = $this->scopeTournaments(Tournament::withCount('teams'))
            ->with(['teams', 'matches.winner', 'matches.team1', 'matches.team2', 'tournamentDay', 'subFolder'])
            ->whereNull('archived_at')
            ->orderByDesc('created_at')
            ->get();

        $archivedTournaments = $this->scopeTournaments(Tournament::withCount('teams'))
            ->with(['teams', 'matches.winner', 'matches.team1', 'matches.team2'])
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->get();

        $allPlayers = $user && $user->isPlayer()
            ? collect()
            : $this->scopeVenue(Player::select('id', 'name'))->orderBy('name')->get();

        if ($user && $user->isPlayer()) {
            $requestDayIds = TournamentRequest::query()
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereNotNull('tournament_day_id')
                ->pluck('tournament_day_id');
            $dayIds = $tournaments->pluck('tournament_day_id')->filter()->merge($requestDayIds)->unique()->values();

            $tournamentDays = TournamentDay::withCount('tournaments')
                ->with('venue:id,name')
                ->whereIn('id', $dayIds)
                ->orderByDesc('date')
                ->get();

            $tournamentSubFolders = TournamentSubFolder::withCount('tournaments')
                ->with('assignedScorer:id,name')
                ->whereIn('tournament_day_id', $dayIds)
                ->orderBy('order')
                ->orderBy('id')
                ->get();
        } else {
            $tournamentDays = $this->scopeVenue(TournamentDay::withCount('tournaments')->with('venue:id,name'))->orderByDesc('date')->get();
            $tournamentSubFolders = $this->scopeVenue(TournamentSubFolder::withCount('tournaments'))->with('assignedScorer:id,name')->orderBy('order')->orderBy('id')->get();
        }

        $scorers = $user && $user->isPlayer()
            ? collect()
            : $this->availableScorersQuery()->orderBy('name')->get();

        $courtCount = (int) (($user?->currentVenue()?->court_count) ?? (\App\Models\SystemSetting::where('key', 'court_count')->value('value') ?? 1));
        $tournamentRequests = $user && $user->isPlayer()
            ? collect()
            : $this->scopeVenue(TournamentRequest::with([
                'user:id,name,username,email',
                'venue:id,name,address',
                'approver:id,name',
                'tournament:id,name,status',
                'tournamentDay:id,name,date,status',
            ]))->latest()->get();

        return Inertia::render('Tournament', [
            'tournaments' => $tournaments,
            'archivedTournaments' => $archivedTournaments,
            'allPlayers' => $allPlayers,
            'tournamentDays' => $tournamentDays,
            'tournamentSubFolders' => $tournamentSubFolders,
            'authUser' => Auth::user(),
            'scorers' => $scorers,
            'courtCount' => $courtCount,
            'tournamentRequests' => $tournamentRequests,
        ]);
    }

    public function show(Tournament $tournament)
    {
        $user = auth()->user();
        $this->ensureTournamentAccess($tournament);
        $tournament->load(['teams', 'matches.team1', 'matches.team2', 'matches.winner', 'tournamentDay', 'subFolder', 'venue']);

        if ($user && $user->isPlayer()) {
            $allPlayers = Player::query()
                ->where('venue_id', $tournament->venue_id)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
            $requestDayIds = TournamentRequest::query()
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereNotNull('tournament_day_id')
                ->pluck('tournament_day_id');
            $dayIds = $this->scopeTournaments(Tournament::query())
                ->pluck('tournament_day_id')
                ->filter()
                ->merge($requestDayIds)
                ->unique()
                ->values();
            $tournamentDays = TournamentDay::query()
                ->with('venue:id,name')
                ->whereIn('id', $dayIds)
                ->withCount('tournaments')
                ->orderByDesc('date')
                ->get();
            $tournamentSubFolders = TournamentSubFolder::query()
                ->whereIn('tournament_day_id', $dayIds)
                ->withCount('tournaments')
                ->with('assignedScorer:id,name')
                ->orderBy('order')
                ->orderBy('id')
                ->get();
            $scorers = User::query()
                ->whereIn('role', ['scorer', 'scheduler_scorer'])
                ->where('venue_id', $tournament->venue_id)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
            $courtCount = (int) ($tournament->venue?->court_count ?? 1);
            $tournamentRequests = collect();
        } else {
            $allPlayers = $this->scopeVenue(Player::select('id', 'name'))->orderBy('name')->get();
            $tournamentDays = $this->scopeVenue(TournamentDay::withCount('tournaments')->with('venue:id,name'))->orderByDesc('date')->get();
            $tournamentSubFolders = $this->scopeVenue(TournamentSubFolder::withCount('tournaments'))->with('assignedScorer:id,name')->orderBy('order')->orderBy('id')->get();
            $scorers = $this->availableScorersQuery()->orderBy('name')->get();
            $courtCount = (int) (($user?->currentVenue()?->court_count) ?? (\App\Models\SystemSetting::where('key', 'court_count')->value('value') ?? 1));
            $tournamentRequests = $this->scopeVenue(TournamentRequest::with([
                'user:id,name,username,email',
                'venue:id,name,address',
                'approver:id,name',
                'tournament:id,name,status',
                'tournamentDay:id,name,date,status',
            ]))->latest()->get();
        }

        return Inertia::render('Tournament', [
            'tournaments' => $this->scopeTournaments(Tournament::withCount('teams'))
                ->with(['teams', 'matches.winner', 'matches.team1', 'matches.team2', 'tournamentDay', 'subFolder'])
                ->orderByDesc('created_at')
                ->get(),
            'allPlayers' => $allPlayers,
            'activeTournament' => $tournament,
            'tournamentDays' => $tournamentDays,
            'tournamentSubFolders' => $tournamentSubFolders,
            'authUser' => Auth::user(),
            'scorers' => $scorers,
            'courtCount' => $courtCount,
            'tournamentRequests' => $tournamentRequests,
        ]);
    }

    public function publicIndex()
    {
        $tournaments = $this->publicTournamentsQuery()->get();

        return Inertia::render('TournamentSpectator', [
            'tournaments' => $tournaments,
        ]);
    }

    public function publicShow(Tournament $tournament)
    {
        if ($tournament->status === 'setup' || $tournament->archived_at !== null) {
            abort(404);
        }

        $tournament->load(['teams', 'matches.team1', 'matches.team2', 'matches.winner']);

        $tournaments = $this->publicTournamentsQuery()->get();

        return Inertia::render('TournamentSpectator', [
            'tournaments' => $tournaments,
            'activeTournament' => $tournament,
        ]);
    }

    private function publicTournamentsQuery()
    {
        return Tournament::withCount('teams')
            ->with(['teams', 'matches.winner', 'matches.team1', 'matches.team2', 'subFolder'])
            ->whereIn('status', ['in_progress', 'completed'])
            ->whereNull('archived_at')
            ->whereDoesntHave('tournamentDay', function ($q) {
                $q->where('status', 'finished');
            })
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at');
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:single_elimination,double_elimination,round_robin',
            'category' => 'required|in:mens,female,mix',
            'min_players' => 'required|integer|min:2|max:64',
            'max_players' => 'required|integer|min:2|max:64',
            'best_of' => 'required|integer|in:1,3,5',
            'start_time' => 'required|string',
            'match_duration' => 'required|integer|min:1',
            'rest_time' => 'required|integer|min:0',
            'enable_break' => 'required|boolean',
            'break_start' => 'nullable|required_if:enable_break,true|string',
            'break_end' => 'nullable|required_if:enable_break,true|string',
            'tournament_day_id' => 'nullable|exists:tournament_days,id',
            'tournament_sub_folder_id' => 'nullable|exists:tournament_sub_folders,id',
            'assigned_courts' => 'nullable|array',
            'assigned_courts.*' => 'integer|min:1',
        ]);

        if ($validated['enable_break']) {
            $toMins = function($t) {
                if (!$t) return 0;
                $parts = explode(':', $t);
                return ((int)$parts[0]) * 60 + ((int)($parts[1] ?? 0));
            };
            if ($toMins($validated['break_end']) <= $toMins($validated['break_start'])) {
                return redirect()->back()->withErrors(['break_end' => 'Break End Time must be greater than Break Start Time.']);
            }
        }

        $this->ensureAccessibleTournamentDayId(isset($validated['tournament_day_id']) ? (int) $validated['tournament_day_id'] : null);

        if ($user && $user->isPlayer()) {
            if (empty($validated['tournament_day_id'])) {
                return redirect()->back()->withErrors([
                    'tournament_day_id' => 'Choose your approved main folder before creating a tournament card.',
                ]);
            }

            $day = TournamentDay::findOrFail((int) $validated['tournament_day_id']);
            if (in_array($day->status, ['finished', 'archived'], true)) {
                return redirect()->back()->withErrors([
                    'tournament_day_id' => 'This tournament workspace is now view-only. Request edit access from the scheduler to make changes again.',
                ]);
            }
            $validated['venue_id'] = $day->venue_id;
            $validated['manager_user_id'] = $user->id;
            $validated['tournament_sub_folder_id'] = null;
            $validated['assigned_courts'] = $day->assigned_courts;
        }

        if (!empty($validated['tournament_day_id']) && empty($validated['tournament_sub_folder_id'])) {
            $day = TournamentDay::find((int) $validated['tournament_day_id']);
            if ($day) {
                $validated['assigned_courts'] = $day->assigned_courts;
                $validated['venue_id'] = $validated['venue_id'] ?? $day->venue_id;
            }
        }

        // For double elimination, force min and max to the same power-of-2 value
        if ($validated['type'] === 'double_elimination') {
            $validCounts = [4, 8, 16, 32];
            if (!in_array($validated['max_players'], $validCounts)) {
                // Snap to nearest valid count
                $validated['max_players'] = collect($validCounts)->sort()->first(fn($v) => $v >= $validated['max_players']) ?? 32;
            }
            $validated['min_players'] = $validated['max_players'];
        }

        // Inherit subfolder schedule settings if created directly inside a subfolder
        if (isset($validated['tournament_sub_folder_id']) && $validated['tournament_sub_folder_id']) {
            $subFolder = $this->ensureAccessibleSubFolderId((int) $validated['tournament_sub_folder_id']);
            if ($subFolder) {
                $this->ensureAccessibleTournamentDayId((int) $subFolder->tournament_day_id);
                $validated['start_time'] = $subFolder->start_time;
                $validated['match_duration'] = $subFolder->match_duration;
                $validated['rest_time'] = $subFolder->rest_time;
                $validated['enable_break'] = $subFolder->enable_break;
                $validated['break_start'] = $subFolder->break_start;
                $validated['break_end'] = $subFolder->break_end;
                $validated['assigned_courts'] = $subFolder->assigned_courts;
                $validated['venue_id'] = $validated['venue_id'] ?? $subFolder->venue_id;
            }
        }

        if (!$user || !$user->isPlayer()) {
            $validated['venue_id'] = $this->activeVenueId();
        }
        $tournament = Tournament::create($validated);

        return redirect()->route('tournaments.show', $tournament->id);
    }

    public function addTeam(Request $request, Tournament $tournament)
    {
        $this->ensureTournamentAccess($tournament);
        $this->ensureTournamentEditable($tournament);
        $validated = $request->validate([
            'player1_name' => 'required|string|max:255',
            'player2_name' => 'required|string|max:255',
        ]);

        $teamCount = $tournament->teams()->count();
        if ($teamCount >= $tournament->max_players) {
            return redirect()->back()->withErrors(['error' => 'Tournament is full.']);
        }

        $p1 = strtolower(trim($validated['player1_name']));
        $p2 = strtolower(trim($validated['player2_name']));

        if ($p1 === $p2) {
            return redirect()->back()->withErrors(['error' => 'Both players on a team must be different.']);
        }

        $existing = $this->findExistingPlayer($tournament, $p1, $p2);
        if ($existing) {
            return redirect()->back()->withErrors(['error' => "Player '{$existing}' is already paired in another team."]);
        }

        $tournament->teams()->create([
            'player1_name' => $validated['player1_name'],
            'player2_name' => $validated['player2_name'],
            'seed' => $teamCount + 1,
        ]);

        $this->regenerateBracketIfNeeded($tournament);

        return redirect()->route('tournaments.show', $tournament->id);
    }

    public function removeTeam(Tournament $tournament, TournamentPlayer $team)
    {
        $this->ensureTournamentAccess($tournament);
        $this->ensureTournamentEditable($tournament);
        $team->delete();

        // Re-seed remaining teams
        $remaining = $tournament->teams()->orderBy('seed')->get();
        foreach ($remaining as $i => $t) {
            $t->update(['seed' => $i + 1]);
        }

        $this->regenerateBracketIfNeeded($tournament);

        return redirect()->route('tournaments.show', $tournament->id);
    }

    public function generateBracket(Tournament $tournament)
    {
        $this->ensureTournamentAccess($tournament);
        $this->ensureTournamentEditable($tournament);
        $teams = $tournament->teams()->get()->shuffle();
        $teamCount = $teams->count();

        if ($teamCount < $tournament->min_players) {
            return redirect()->back()->withErrors(['error' => "Need at least {$tournament->min_players} teams to start."]);
        }

        // Double elimination requires exactly a power of 2: 4, 8, 16, or 32
        if ($tournament->type === 'double_elimination') {
            $validCounts = [4, 8, 16, 32];
            if (!in_array($teamCount, $validCounts)) {
                return redirect()->back()->withErrors(['error' => "Double elimination requires exactly 4, 8, 16, or 32 teams. You have {$teamCount} teams."]);
            }
        }

        // Clear existing matches
        $tournament->matches()->delete();

        match ($tournament->type) {
            'single_elimination' => $this->generateSingleElimination($tournament, $teams),
            'double_elimination' => $this->generateDoubleElimination($tournament, $teams),
            'round_robin' => $this->generateRoundRobin($tournament, $teams),
        };

        $tournament->update(['status' => 'in_progress']);

        // Generate schedule times for matches
        if ($tournament->tournament_sub_folder_id) {
            $subFolder = $tournament->subFolder;
            if ($subFolder) {
                // Sync tournament settings to subfolder settings
                $tournament->update([
                    'start_time' => $subFolder->start_time,
                    'match_duration' => $subFolder->match_duration,
                    'rest_time' => $subFolder->rest_time,
                    'enable_break' => $subFolder->enable_break,
                    'break_start' => $subFolder->break_start,
                    'break_end' => $subFolder->break_end,
                ]);

                $tournamentsInGroup = $subFolder->tournaments()
                    ->where('status', 'in_progress')
                    ->where('type', $tournament->type)
                    ->get();
                Tournament::generateSharedMatchSchedules($tournamentsInGroup, [
                    'start_time' => $subFolder->start_time,
                    'match_duration' => $subFolder->match_duration,
                    'rest_time' => $subFolder->rest_time,
                    'enable_break' => $subFolder->enable_break,
                    'break_start' => $subFolder->break_start,
                    'break_end' => $subFolder->break_end,
                    'court_count' => is_array($subFolder->assigned_courts) ? count($subFolder->assigned_courts) : 1,
                ]);
                Tournament::assignCourtsDynamically($subFolder->id);
            } else {
                $tournament->generateMatchSchedules();
                Tournament::assignCourtsDynamicallyForTournament($tournament->id);
            }
        } else {
            $tournament->generateMatchSchedules();
            Tournament::assignCourtsDynamicallyForTournament($tournament->id);
        }

        return redirect()->route('tournaments.show', $tournament->id);
    }

    public function recordScore(Request $request, TournamentMatch $match)
    {
        $this->ensureTournamentAccess($match->tournament);
        $this->ensureTournamentEditable($match->tournament);

        if ($match->team1_id === null || $match->team2_id === null) {
            return redirect()->back()->withErrors(['error' => 'Both teams must be assigned before recording a score.']);
        }

        $validated = $request->validate([
            'team1_score' => 'required|integer|min:0|max:99',
            'team2_score' => 'required|integer|min:0|max:99',
        ]);

        if ($validated['team1_score'] === $validated['team2_score']) {
            return redirect()->back()->withErrors(['error' => 'Scores cannot be tied.']);
        }

        $bestOf = (int) ($match->tournament->best_of ?? 1);
        $gamesNeeded = (int) ceil($bestOf / 2);
        $higherScore = max($validated['team1_score'], $validated['team2_score']);
        if ($higherScore < $gamesNeeded) {
            return redirect()->back()->withErrors([
                'error' => "Winner must reach {$gamesNeeded} games to win this best-of-{$bestOf} match.",
            ]);
        }

        $winnerId = $validated['team1_score'] > $validated['team2_score']
            ? $match->team1_id
            : $match->team2_id;

        $loserId = $winnerId === $match->team1_id ? $match->team2_id : $match->team1_id;

        $match->update([
            'team1_score' => $validated['team1_score'],
            'team2_score' => $validated['team2_score'],
            'winner_id' => $winnerId,
        ]);

        // Advance winner to next match
        if ($match->next_match_id) {
            $nextMatch = TournamentMatch::find($match->next_match_id);
            if ($nextMatch) {
                $nextMatch->update([$match->next_match_slot . '_id' => $winnerId]);
            }
        }

        // For double elimination: send loser to losers bracket
        if ($match->loser_next_match_id) {
            $loserMatch = TournamentMatch::find($match->loser_next_match_id);
            if ($loserMatch) {
                $loserMatch->update([$match->loser_next_match_slot . '_id' => $loserId]);
            }
        }

        // Check if tournament is complete
        $tournament = $match->tournament;
        $allMatches = $tournament->matches;
        $allPlayed = $allMatches->every(fn($m) => $m->winner_id !== null || ($m->team1_id === null && $m->team2_id === null));

        if ($allPlayed) {
            $tournament->update(['status' => 'completed']);
        }

        if ($tournament->tournament_sub_folder_id) {
            Tournament::assignCourtsDynamically($tournament->tournament_sub_folder_id);
        } else {
            Tournament::assignCourtsDynamicallyForTournament($tournament->id);
        }

        return redirect()->route('tournaments.show', $tournament->id);
    }

    public function resetMatch(TournamentMatch $match)
    {
        $tournament = $match->tournament;
        $this->ensureTournamentAccess($tournament);
        $this->ensureTournamentEditable($tournament);

        // Recursively reset downstream matches first
        if ($match->next_match_id) {
            $nextMatch = TournamentMatch::find($match->next_match_id);
            if ($nextMatch) {
                // Clear the slot this match feeds into
                $slot = $match->next_match_slot . '_id';
                if ($nextMatch->$slot === $match->winner_id) {
                    $nextMatch->update([$slot => null]);
                }
                // If next match already has a winner, recursively reset it
                if ($nextMatch->winner_id !== null) {
                    $this->resetMatch($nextMatch);
                }
            }
        }

        // For double elimination: also reset loser bracket
        if ($match->loser_next_match_id) {
            $loserMatch = TournamentMatch::find($match->loser_next_match_id);
            if ($loserMatch) {
                $slot = $match->loser_next_match_slot . '_id';
                $loserTeamId = $match->team1_score > $match->team2_score ? $match->team2_id : $match->team1_id;
                if ($loserMatch->$slot === $loserTeamId) {
                    $loserMatch->update([$slot => null]);
                }
                if ($loserMatch->winner_id !== null) {
                    $this->resetMatch($loserMatch);
                }
            }
        }

        // Clear this match
        $match->update([
            'team1_score' => null,
            'team2_score' => null,
            'winner_id' => null,
            'is_forfeited' => false,
            'bypass_count' => 0,
        ]);

        // Revert tournament status if it was completed
        if ($tournament->status === 'completed') {
            $tournament->update(['status' => 'in_progress']);
        }

        if ($tournament->tournament_sub_folder_id) {
            Tournament::assignCourtsDynamically($tournament->tournament_sub_folder_id);
        } else {
            Tournament::assignCourtsDynamicallyForTournament($tournament->id);
        }

        return redirect()->route('tournaments.show', $tournament->id);
    }

    public function bypassMatch(TournamentMatch $match)
    {
        $this->ensureTournamentAccess($match->tournament);
        $tournament = $match->tournament;
        $this->ensureTournamentEditable($tournament);

        if ($tournament->status === 'completed') {
            return redirect()->back()->withErrors(['error' => 'Cannot edit matches in a completed tournament.']);
        }

        if ($match->winner_id !== null) {
            return redirect()->back()->withErrors(['error' => 'Cannot bypass a match that has already been played.']);
        }

        $match->increment('bypass_count');
        $match->update(['court_number' => null]);

        if ($tournament->tournament_sub_folder_id) {
            Tournament::assignCourtsDynamically($tournament->tournament_sub_folder_id, null, true);
        } else {
            Tournament::assignCourtsDynamicallyForTournament($tournament->id, null, true);
        }

        return redirect()->route('tournaments.show', $tournament->id);
    }

    public function forfeitMatch(Request $request, TournamentMatch $match)
    {
        $this->ensureTournamentAccess($match->tournament);
        $tournament = $match->tournament;
        $this->ensureTournamentEditable($tournament);

        if ($tournament->status === 'completed') {
            return redirect()->back()->withErrors(['error' => 'Cannot edit matches in a completed tournament.']);
        }

        if ($match->winner_id !== null) {
            return redirect()->back()->withErrors(['error' => 'Cannot forfeit a match that has already been played.']);
        }

        if ($match->team1_id === null || $match->team2_id === null) {
            return redirect()->back()->withErrors(['error' => 'Both teams must be assigned before forfeiting.']);
        }

        $validated = $request->validate([
            'winner_id' => 'required|integer|exists:tournament_players,id',
            'winning_score' => 'required|integer|min:0|max:99',
        ]);

        $winnerId = (int) $validated['winner_id'];
        if ($winnerId !== (int) $match->team1_id && $winnerId !== (int) $match->team2_id) {
            return redirect()->back()->withErrors(['error' => 'Winner must be one of the teams in the match.']);
        }

        $winningScore = (int) $validated['winning_score'];

        $team1Score = $winnerId === (int) $match->team1_id ? $winningScore : 0;
        $team2Score = $winnerId === (int) $match->team2_id ? $winningScore : 0;

        $match->update([
            'team1_score' => $team1Score,
            'team2_score' => $team2Score,
            'winner_id' => $winnerId,
            'is_forfeited' => true,
        ]);

        $loserId = $winnerId === (int) $match->team1_id ? $match->team2_id : $match->team1_id;

        // Advance winner to next match
        if ($match->next_match_id) {
            $nextMatch = TournamentMatch::find($match->next_match_id);
            if ($nextMatch) {
                $nextMatch->update([$match->next_match_slot . '_id' => $winnerId]);
            }
        }

        // For double elimination: send loser to losers bracket
        if ($match->loser_next_match_id) {
            $loserMatch = TournamentMatch::find($match->loser_next_match_id);
            if ($loserMatch) {
                $loserMatch->update([$match->loser_next_match_slot . '_id' => $loserId]);
            }
        }

        // Check if tournament is complete
        $allMatches = $tournament->matches;
        $allPlayed = $allMatches->every(fn($m) => $m->winner_id !== null || ($m->team1_id === null && $m->team2_id === null));

        if ($allPlayed) {
            $tournament->update(['status' => 'completed']);
        }

        if ($tournament->tournament_sub_folder_id) {
            Tournament::assignCourtsDynamically($tournament->tournament_sub_folder_id);
        } else {
            Tournament::assignCourtsDynamicallyForTournament($tournament->id);
        }

        return redirect()->route('tournaments.show', $tournament->id);
    }

    public function updateTeam(Request $request, Tournament $tournament, TournamentPlayer $team)
    {
        $this->ensureTournamentAccess($tournament);
        $this->ensureTournamentEditable($tournament);
        $validated = $request->validate([
            'player1_name' => 'required|string|max:255',
            'player2_name' => 'required|string|max:255',
        ]);

        // Prevent editing if tournament is completed
        if ($tournament->status === 'completed') {
            return redirect()->back()->withErrors(['error' => 'Cannot edit teams in a completed tournament.']);
        }

        // Prevent editing if any match has already been played
        $hasPlayedMatches = $tournament->matches()->whereNotNull('winner_id')->exists();
        if ($tournament->status === 'in_progress' && $hasPlayedMatches) {
            return redirect()->back()->withErrors(['error' => 'Cannot edit teams after matches have been played.']);
        }

        $p1 = strtolower(trim($validated['player1_name']));
        $p2 = strtolower(trim($validated['player2_name']));

        if ($p1 === $p2) {
            return redirect()->back()->withErrors(['error' => 'Both players on a team must be different.']);
        }

        $existing = $this->findExistingPlayer($tournament, $p1, $p2, $team->id);
        if ($existing) {
            return redirect()->back()->withErrors(['error' => "Player '{$existing}' is already paired in another team."]);
        }

        $team->update([
            'player1_name' => $validated['player1_name'],
            'player2_name' => $validated['player2_name'],
        ]);

        return redirect()->route('tournaments.show', $tournament->id);
    }

    public function updateMatchTeams(Request $request, TournamentMatch $match)
    {
        $this->ensureTournamentAccess($match->tournament);
        $this->ensureTournamentEditable($match->tournament);
        $validated = $request->validate([
            'team1_id' => 'nullable|integer|exists:tournament_players,id',
            'team2_id' => 'nullable|integer|exists:tournament_players,id',
            'scheduled_time' => 'nullable|string',
            'court_number' => 'nullable|integer|min:1',
        ]);

        $tournament = $match->tournament;

        if ($tournament->status === 'completed') {
            return redirect()->back()->withErrors(['error' => 'Cannot edit matches in a completed tournament.']);
        }

        if ($match->winner_id !== null) {
            return redirect()->back()->withErrors(['error' => 'Cannot edit a match that has already been played.']);
        }

        // Verify teams belong to this tournament
        if (isset($validated['team1_id']) && $validated['team1_id']) {
            $belongs = $tournament->teams()->where('id', $validated['team1_id'])->exists();
            if (!$belongs) {
                return redirect()->back()->withErrors(['error' => 'Team 1 does not belong to this tournament.']);
            }
        }
        if (isset($validated['team2_id']) && $validated['team2_id']) {
            $belongs = $tournament->teams()->where('id', $validated['team2_id'])->exists();
            if (!$belongs) {
                return redirect()->back()->withErrors(['error' => 'Team 2 does not belong to this tournament.']);
            }
        }

        $updateData = [
            'team1_id' => $validated['team1_id'] ?? null,
            'team2_id' => $validated['team2_id'] ?? null,
            'scheduled_time' => $validated['scheduled_time'] ?? $match->scheduled_time,
        ];

        if (array_key_exists('court_number', $validated)) {
            $updateData['court_number'] = $validated['court_number'];
        }

        $match->update($updateData);

        if ($tournament->tournament_sub_folder_id) {
            Tournament::assignCourtsDynamically($tournament->tournament_sub_folder_id);
        } else {
            Tournament::assignCourtsDynamicallyForTournament($tournament->id);
        }

        return redirect()->route('tournaments.show', $tournament->id);
    }

    public function destroy(Tournament $tournament)
    {
        $this->ensureTournamentAccess($tournament);
        $user = auth()->user();

        if ($user?->isPlayer()) {
            $this->ensureTournamentEditable($tournament);
        }

        $tournament->delete();
        return redirect()->route('tournaments.index');
    }

    public function bulkDestroy(Request $request)
    {
        $user = auth()->user();

        if (!$user?->isAdmin() && !$user?->isPlayer()) {
            $request->merge(['venue_id' => $this->activeVenueId()]);
        }
        $validated = $request->validate([
            'tournament_ids'   => 'required|array|min:1',
            'tournament_ids.*' => 'integer|exists:tournaments,id',
        ]);

        $count = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $user) {
            $query = Tournament::whereIn('id', $validated['tournament_ids']);

            if ($user?->isPlayer()) {
                $query->where('manager_user_id', $user->id);
                $tournaments = $query->get();

                foreach ($tournaments as $tournament) {
                    $this->ensureTournamentEditable($tournament);
                }

                $count = $tournaments->count();
                if ($count > 0) {
                    Tournament::whereIn('id', $tournaments->pluck('id'))->delete();
                }

                return $count;
            }

            if (!$user?->isAdmin() && !empty($validated['venue_id'])) {
                $query->where('venue_id', $validated['venue_id']);
            }

            return $query->delete();
        });

        return back()->with('success', "{$count} tournament(s) deleted.");
    }

    public function archive(Tournament $tournament)
    {
        $this->ensureTournamentAccess($tournament);
        if (auth()->user()?->isPlayer()) {
            abort(403, 'Players cannot archive the tournament workspace directly.');
        }
        $tournament->update(['archived_at' => now()]);

        if (request()->wantsJson() || request()->header('X-Inertia')) {
            return back();
        }
        return back();
    }

    public function archiveCompleted()
    {
        $query = Tournament::where('status', 'completed');
        if (!auth()->user()?->isAdmin()) {
            $query->where('venue_id', $this->activeVenueId());
        }
        $count = $query
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);

        return back()->with('success', "{$count} completed tournament(s) archived.");
    }

    public function unarchive(Tournament $tournament)
    {
        $this->ensureTournamentAccess($tournament);
        if (auth()->user()?->isPlayer()) {
            abort(403, 'Players cannot unarchive the tournament workspace directly.');
        }
        $tournament->update(['archived_at' => null]);
        return back();
    }

    public function swapOpponents(Request $request, TournamentMatch $match)
    {
        $this->ensureTournamentAccess($match->tournament);
        $this->ensureTournamentEditable($match->tournament);
        $validated = $request->validate([
            'other_match_id' => 'required|integer|exists:tournament_matches,id',
        ]);

        $tournament = $match->tournament;

        if ($tournament->status === 'completed') {
            return redirect()->back()->withErrors(['error' => 'Cannot swap opponents in a completed tournament.']);
        }

        if ($match->winner_id !== null) {
            return redirect()->back()->withErrors(['error' => 'Cannot swap opponents in a match that has already been played.']);
        }

        if ($match->round !== 1) {
            return redirect()->back()->withErrors(['error' => 'Swapping opponents is only allowed in the first round.']);
        }

        $otherMatch = TournamentMatch::findOrFail($validated['other_match_id']);

        if ($otherMatch->tournament_id !== $tournament->id) {
            return redirect()->back()->withErrors(['error' => 'Match does not belong to this tournament.']);
        }

        if ($otherMatch->round !== 1) {
            return redirect()->back()->withErrors(['error' => 'Both matches must be in the first round.']);
        }

        if ($otherMatch->winner_id !== null) {
            return redirect()->back()->withErrors(['error' => 'Cannot swap with a match that has already been played.']);
        }

        if ($otherMatch->id === $match->id) {
            return redirect()->back()->withErrors(['error' => 'Cannot swap a match with itself.']);
        }

        if ($otherMatch->bracket !== $match->bracket) {
            return redirect()->back()->withErrors(['error' => 'Both matches must be in the same bracket.']);
        }

        // Swap team2_id between the two matches
        $tempTeam2 = $match->team2_id;
        $match->update(['team2_id' => $otherMatch->team2_id]);
        $otherMatch->update(['team2_id' => $tempTeam2]);

        return redirect()->route('tournaments.show', $tournament->id);
    }

    // --- Bracket Generation Logic ---

    private function generateSingleElimination(Tournament $tournament, $teams)
    {
        $count = $teams->count();
        $totalSlots = 1;
        while ($totalSlots < $count) $totalSlots *= 2;
        $totalRounds = (int) log($totalSlots, 2);

        // Create all matches for all rounds
        $matchesByRound = [];
        for ($round = 1; $round <= $totalRounds; $round++) {
            $matchesInRound = $totalSlots / pow(2, $round);
            $matchesByRound[$round] = [];
            for ($m = 0; $m < $matchesInRound; $m++) {
                $match = TournamentMatch::create([
                    'tournament_id' => $tournament->id,
                    'round' => $round,
                    'match_order' => $m + 1,
                    'bracket' => 'winners',
                    'venue_id' => $tournament->venue_id,
                ]);
                $matchesByRound[$round][] = $match;
            }
        }

        // Link matches: winner of round N match M goes to round N+1 match floor(M/2)
        for ($round = 1; $round < $totalRounds; $round++) {
            foreach ($matchesByRound[$round] as $i => $match) {
                $nextMatchIndex = intdiv($i, 2);
                $slot = ($i % 2 === 0) ? 'team1' : 'team2';
                $match->update([
                    'next_match_id' => $matchesByRound[$round + 1][$nextMatchIndex]->id,
                    'next_match_slot' => $slot,
                ]);
            }
        }

        // Place shuffled teams sequentially into round 1
        $teamIndex = 0;
        foreach ($matchesByRound[1] as $i => $match) {
            $team1 = ($teamIndex < $count) ? $teams[$teamIndex++] : null;
            $team2 = ($teamIndex < $count) ? $teams[$teamIndex++] : null;

            $update = [];
            if ($team1) $update['team1_id'] = $team1->id;
            if ($team2) $update['team2_id'] = $team2->id;
            $match->update($update);

            // Bye: if one team is missing, auto-advance the other
            if ($team1 && !$team2) {
                $match->update(['winner_id' => $team1->id]);
                if ($match->next_match_id) {
                    TournamentMatch::find($match->next_match_id)
                        ->update([$match->next_match_slot . '_id' => $team1->id]);
                }
            } elseif ($team2 && !$team1) {
                $match->update(['winner_id' => $team2->id]);
                if ($match->next_match_id) {
                    TournamentMatch::find($match->next_match_id)
                        ->update([$match->next_match_slot . '_id' => $team2->id]);
                }
            }
        }
    }

    private function generateDoubleElimination(Tournament $tournament, $teams)
    {
        $count = $teams->count();
        $totalSlots = 1;
        while ($totalSlots < $count) $totalSlots *= 2;
        $winnersRounds = (int) log($totalSlots, 2);
        $losersRounds = 2 * ($winnersRounds - 1);

        // Winners bracket
        $winnerMatches = [];
        for ($round = 1; $round <= $winnersRounds; $round++) {
            $matchesInRound = $totalSlots / pow(2, $round);
            $winnerMatches[$round] = [];
            for ($m = 0; $m < $matchesInRound; $m++) {
                $match = TournamentMatch::create([
                    'tournament_id' => $tournament->id,
                    'round' => $round,
                    'match_order' => $m + 1,
                    'bracket' => 'winners',
                    'venue_id' => $tournament->venue_id,
                ]);
                $winnerMatches[$round][] = $match;
            }
        }

        // Link winners bracket
        for ($round = 1; $round < $winnersRounds; $round++) {
            foreach ($winnerMatches[$round] as $i => $match) {
                $nextIdx = intdiv($i, 2);
                $slot = ($i % 2 === 0) ? 'team1' : 'team2';
                $match->update([
                    'next_match_id' => $winnerMatches[$round + 1][$nextIdx]->id,
                    'next_match_slot' => $slot,
                ]);
            }
        }

        // Losers bracket
        $loserMatches = [];
        for ($lr = 1; $lr <= $losersRounds; $lr++) {
            $matchesInRound = intdiv($totalSlots, pow(2, intdiv($lr + 1, 2) + 1));
            $loserMatches[$lr] = [];
            for ($m = 0; $m < max(1, $matchesInRound); $m++) {
                $match = TournamentMatch::create([
                    'tournament_id' => $tournament->id,
                    'round' => $lr,
                    'match_order' => $m + 1,
                    'bracket' => 'losers',
                    'venue_id' => $tournament->venue_id,
                ]);
                $loserMatches[$lr][] = $match;
            }
        }

        // Link losers bracket internally
        // Odd L round → Even L round: 1:1 mapping (each match feeds same-index next round)
        // Even L round → Odd L round: standard halving
        for ($lr = 1; $lr < $losersRounds; $lr++) {
            foreach ($loserMatches[$lr] as $i => $match) {
                if ($lr % 2 === 1) {
                    $nextIdx = $i;
                    $slot = 'team1';
                } else {
                    $nextIdx = intdiv($i, 2);
                    $slot = ($i % 2 === 0) ? 'team1' : 'team2';
                }
                if (isset($loserMatches[$lr + 1][$nextIdx])) {
                    $match->update([
                        'next_match_id' => $loserMatches[$lr + 1][$nextIdx]->id,
                        'next_match_slot' => $slot,
                    ]);
                }
            }
        }

        // Link winners bracket losers to losers bracket
        // W Round 1 losers → L Round 1 (pair up)
        if (isset($winnerMatches[1]) && isset($loserMatches[1])) {
            foreach ($winnerMatches[1] as $i => $match) {
                $loserIdx = intdiv($i, 2);
                $slot = ($i % 2 === 0) ? 'team1' : 'team2';
                if (isset($loserMatches[1][$loserIdx])) {
                    $match->update([
                        'loser_next_match_id' => $loserMatches[1][$loserIdx]->id,
                        'loser_next_match_slot' => $slot,
                    ]);
                }
            }
        }

        // W Round r losers → L Round (2r - 2) as team2, for r >= 2
        for ($round = 2; $round <= $winnersRounds; $round++) {
            $loserRound = 2 * ($round - 1);
            if (!isset($winnerMatches[$round]) || !isset($loserMatches[$loserRound])) continue;
            foreach ($winnerMatches[$round] as $i => $match) {
                if (isset($loserMatches[$loserRound][$i])) {
                    $match->update([
                        'loser_next_match_id' => $loserMatches[$loserRound][$i]->id,
                        'loser_next_match_slot' => 'team2',
                    ]);
                }
            }
        }

        // Grand final
        $grandFinal = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => $winnersRounds + 1,
            'match_order' => 1,
            'bracket' => 'grand_final',
            'venue_id' => $tournament->venue_id,
        ]);

        // Winners final -> grand final team1
        $winnersFinal = end($winnerMatches[$winnersRounds]);
        $winnersFinal->update([
            'next_match_id' => $grandFinal->id,
            'next_match_slot' => 'team1',
        ]);

        // Losers final -> grand final team2
        if (!empty($loserMatches[$losersRounds])) {
            $losersFinal = end($loserMatches[$losersRounds]);
            $losersFinal->update([
                'next_match_id' => $grandFinal->id,
                'next_match_slot' => 'team2',
            ]);
        }

        // Place shuffled teams sequentially into round 1
        // Since count is guaranteed to be a power of 2, all slots are filled (no byes)
        $teamIndex = 0;
        foreach ($winnerMatches[1] as $i => $match) {
            $match->update([
                'team1_id' => $teams[$teamIndex]->id,
                'team2_id' => $teams[$teamIndex + 1]->id,
            ]);
            $teamIndex += 2;
        }
    }

    private function generateRoundRobin(Tournament $tournament, $teams)
    {
        $teamIds = $teams->pluck('id')->toArray();
        $count = count($teamIds);

        // If odd number, add a null (bye)
        if ($count % 2 !== 0) {
            $teamIds[] = null;
            $count++;
        }

        $totalRounds = $count - 1;
        $matchOrder = 1;

        for ($round = 1; $round <= $totalRounds; $round++) {
            for ($i = 0; $i < $count / 2; $i++) {
                $home = $teamIds[$i];
                $away = $teamIds[$count - 1 - $i];

                if ($home === null || $away === null) continue;

                TournamentMatch::create([
                    'tournament_id' => $tournament->id,
                    'round' => $round,
                    'match_order' => $matchOrder++,
                    'bracket' => 'round_robin',
                    'team1_id' => $home,
                    'team2_id' => $away,
                    'venue_id' => $tournament->venue_id,
                ]);
            }

            // Rotate: fix first element, rotate the rest
            $last = array_pop($teamIds);
            array_splice($teamIds, 1, 0, [$last]);
        }
    }

    private function findExistingPlayer(Tournament $tournament, string $p1, string $p2, ?int $excludeTeamId = null): ?string
    {
        $query = $tournament->teams();
        if ($excludeTeamId) {
            $query->where('id', '!=', $excludeTeamId);
        }

        $teams = $query->get();
        foreach ($teams as $t) {
            $t1 = strtolower(trim($t->player1_name));
            $t2 = strtolower(trim($t->player2_name));
            if ($t1 === $p1 || $t1 === $p2 || $t2 === $p1 || $t2 === $p2) {
                if ($t1 === $p1) return $t->player1_name;
                if ($t2 === $p1) return $t->player2_name;
                if ($t1 === $p2) return $t->player1_name;
                if ($t2 === $p2) return $t->player2_name;
            }
        }
        return null;
    }

    private function regenerateBracketIfNeeded(Tournament $tournament): void
    {
        if ($tournament->status !== 'in_progress') {
            return;
        }

        // Do NOT regenerate if any match has already been played
        $hasPlayed = $tournament->matches()->whereNotNull('winner_id')->exists();
        if ($hasPlayed) {
            return;
        }

        $teams = $tournament->teams()->get()->shuffle();
        $teamCount = $teams->count();

        // For double elimination, only regenerate if count is a valid power of 2
        if ($tournament->type === 'double_elimination') {
            $validCounts = [4, 8, 16, 32];
            if (!in_array($teamCount, $validCounts)) {
                return;
            }
        }

        // Clear existing matches and regenerate
        $tournament->matches()->delete();

        match ($tournament->type) {
            'single_elimination' => $this->generateSingleElimination($tournament, $teams),
            'double_elimination' => $this->generateDoubleElimination($tournament, $teams),
            'round_robin' => $this->generateRoundRobin($tournament, $teams),
        };

        $tournament->generateMatchSchedules();
    }

    private function getSeededOrder(int $size): array
    {
        if ($size === 1) return [0];
        $half = $this->getSeededOrder($size / 2);
        $result = [];
        foreach ($half as $seed) {
            $result[] = $seed;
            $result[] = $size - 1 - $seed;
        }
        return $result;
    }



    public function updateScheduleSettings(Request $request, Tournament $tournament)
    {
        $this->ensureTournamentAccess($tournament);
        $this->ensureTournamentEditable($tournament);
        $validated = $request->validate([
            'start_time' => 'required|string',
            'match_duration' => 'required|integer|min:1',
            'rest_time' => 'required|integer|min:0',
            'enable_break' => 'required|boolean',
            'break_start' => 'nullable|required_if:enable_break,true|string',
            'break_end' => 'nullable|required_if:enable_break,true|string',
            'assigned_courts' => 'nullable|array',
            'assigned_courts.*' => 'integer|min:1',
        ]);

        if ($validated['enable_break']) {
            $toMins = function($t) {
                if (!$t) return 0;
                $parts = explode(':', $t);
                return ((int)$parts[0]) * 60 + ((int)($parts[1] ?? 0));
            };
            if ($toMins($validated['break_end']) <= $toMins($validated['break_start'])) {
                return redirect()->back()->withErrors(['break_end' => 'Break End Time must be greater than Break Start Time.']);
            }
        }

        $tournament->update($validated);

        // Recalculate schedules
        if ($tournament->tournament_sub_folder_id) {
            $subFolder = $tournament->subFolder;
            if ($subFolder) {
                $tournamentsInGroup = $subFolder->tournaments()
                    ->where('status', 'in_progress')
                    ->where('type', $tournament->type)
                    ->get();
                Tournament::generateSharedMatchSchedules($tournamentsInGroup, [
                    'start_time' => $tournament->start_time,
                    'match_duration' => $tournament->match_duration,
                    'rest_time' => $tournament->rest_time,
                    'enable_break' => $tournament->enable_break,
                    'break_start' => $tournament->break_start,
                    'break_end' => $tournament->break_end,
                    'court_count' => is_array($subFolder->assigned_courts) ? count($subFolder->assigned_courts) : 1,
                ]);
                Tournament::assignCourtsDynamically($subFolder->id);
            } else {
                $tournament->generateMatchSchedules();
                Tournament::assignCourtsDynamicallyForTournament($tournament->id);
            }
        } else {
            $tournament->generateMatchSchedules();
            Tournament::assignCourtsDynamicallyForTournament($tournament->id);
        }

        return redirect()->route('tournaments.show', $tournament->id);
    }

    public function update(Request $request, Tournament $tournament)
    {
        $this->ensureTournamentAccess($tournament);
        $this->ensureTournamentEditable($tournament);
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'type'        => 'sometimes|required|in:single_elimination,double_elimination,round_robin',
            'category'    => 'sometimes|required|in:mens,female,mix',
            'min_players' => 'sometimes|required|integer|min:2|max:64',
            'max_players' => 'sometimes|required|integer|min:2|max:64',
            'best_of'     => 'sometimes|required|integer|in:1,3,5',
            'tournament_day_id' => 'sometimes|nullable|exists:tournament_days,id',
            'tournament_sub_folder_id' => 'sometimes|nullable|exists:tournament_sub_folders,id',
            'assigned_courts' => 'sometimes|nullable|array',
            'assigned_courts.*' => 'integer|min:1',
        ]);

        $structuralKeys = ['type', 'min_players', 'max_players'];
        if (array_intersect_key($validated, array_flip($structuralKeys)) && $tournament->status !== 'setup') {
            return back()->withErrors(['error' => 'Bracket type and player count can only be changed in setup.']);
        }

        $resolvedType = $validated['type'] ?? $tournament->type;
        if ($resolvedType === 'double_elimination') {
            $valid = [4, 8, 16, 32];
            $max = (int) ($validated['max_players'] ?? $tournament->max_players);
            if (!in_array($max, $valid, true)) {
                return back()->withErrors(['max_players' => 'Double elimination requires max players of 4, 8, 16, or 32.']);
            }
        }

        if (array_key_exists('max_players', $validated)) {
            $currentTeamCount = $tournament->teams()->count();
            if ((int) $validated['max_players'] < $currentTeamCount) {
                return back()->withErrors(['max_players' => "Cannot lower max players below current team count ({$currentTeamCount}). Remove teams first."]);
            }
        }

        if ($resolvedType === 'double_elimination') {
            $validated['min_players'] = $validated['max_players'] ?? $tournament->max_players;
        } elseif (isset($validated['type']) || isset($validated['max_players'])) {
            $max = $validated['max_players'] ?? $tournament->max_players;
            $validated['min_players'] = min($tournament->min_players, $max);
        }

        if (array_key_exists('tournament_day_id', $validated)) {
            $this->ensureAccessibleTournamentDayId($validated['tournament_day_id'] ? (int) $validated['tournament_day_id'] : null);

            if (($validated['tournament_day_id'] ?? null) && !array_key_exists('tournament_sub_folder_id', $validated)) {
                $day = TournamentDay::find($validated['tournament_day_id']);
                if ($day) {
                    $validated['assigned_courts'] = $day->assigned_courts;
                }
            }
        }

        if (array_key_exists('tournament_sub_folder_id', $validated)) {
            $subFolder = $this->ensureAccessibleSubFolderId($validated['tournament_sub_folder_id'] ? (int) $validated['tournament_sub_folder_id'] : null);
            if ($subFolder && (int) $subFolder->tournament_day_id !== (int) ($validated['tournament_day_id'] ?? $tournament->tournament_day_id)) {
                throw ValidationException::withMessages([
                    'tournament_sub_folder_id' => 'The selected sub-folder does not belong to the selected tournament day.',
                ]);
            }

            $validated['assigned_courts'] = $subFolder?->assigned_courts;
        }

        $tournament->update($validated);

        if (!$tournament->tournament_sub_folder_id) {
            Tournament::assignCourtsDynamicallyForTournament($tournament->id);
        }

        return redirect()->route('tournaments.show', $tournament->id);
    }

    public function backToSetup(Tournament $tournament)
    {
        $this->ensureTournamentAccess($tournament);
        $this->ensureTournamentEditable($tournament);
        if ($tournament->status !== 'in_progress') {
            return back()->withErrors(['error' => 'Tournament is not in progress.']);
        }

        $hasPlayed = $tournament->matches()->whereNotNull('winner_id')->exists();
        if ($hasPlayed) {
            return back()->withErrors(['error' => 'Cannot revert to setup after matches have been played.']);
        }

        $tournament->matches()->delete();
        $tournament->update(['status' => 'setup', 'min_players' => $tournament->max_players]);

        return redirect()->route('tournaments.show', $tournament->id);
    }
}
