<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    protected $fillable = [
        'name', 'type', 'category', 'status', 'min_players', 'max_players', 'best_of', 'archived_at',
        'start_time', 'match_duration', 'rest_time', 'enable_break', 'break_start', 'break_end',
        'tournament_day_id', 'tournament_sub_folder_id', 'assigned_courts', 'venue_id', 'manager_user_id'
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'enable_break' => 'boolean',
        'assigned_courts' => 'array',
    ];

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function teams()
    {
        return $this->hasMany(TournamentPlayer::class)->orderBy('seed');
    }

    public function matches()
    {
        return $this->hasMany(TournamentMatch::class)->orderBy('round')->orderBy('match_order');
    }

    public function tournamentDay()
    {
        return $this->belongsTo(TournamentDay::class);
    }

    public function subFolder()
    {
        return $this->belongsTo(TournamentSubFolder::class, 'tournament_sub_folder_id');
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }
    public function generateMatchSchedules()
    {
        $courtCount = 1;
        if ($this->tournament_sub_folder_id) {
            $subFolder = TournamentSubFolder::find($this->tournament_sub_folder_id);
            if ($subFolder && is_array($subFolder->assigned_courts)) {
                $courtCount = count($subFolder->assigned_courts);
            }
        } elseif (is_array($this->assigned_courts)) {
            $courtCount = count($this->assigned_courts);
        }

        self::generateSharedMatchSchedules([$this], [
            'start_time' => $this->start_time,
            'match_duration' => $this->match_duration,
            'rest_time' => $this->rest_time,
            'enable_break' => $this->enable_break,
            'break_start' => $this->break_start,
            'break_end' => $this->break_end,
            'court_count' => $courtCount,
        ]);
    }

    public static function generateSharedMatchSchedules($tournaments, $settings)
    {
        $startTime = $settings['start_time'] ?? '08:00';
        $duration = $settings['match_duration'] ?? 25;
        $restTime = $settings['rest_time'] ?? 5;
        $enableBreak = (bool)($settings['enable_break'] ?? false);
        $breakStart = $settings['break_start'] ?? null;
        $breakEnd = $settings['break_end'] ?? null;
        $courtCount = (int)($settings['court_count'] ?? 1);
        if ($courtCount < 1) $courtCount = 1;

        $toMinutes = function($timeStr) {
            if (!$timeStr) return 0;
            $parts = explode(':', $timeStr);
            return ((int)$parts[0]) * 60 + ((int)($parts[1] ?? 0));
        };

        $toTimeStr = function($mins) {
            $hours = intdiv($mins, 60) % 24;
            $m = $mins % 60;
            return sprintf('%02d:%02d', $hours, $m);
        };

        $currentMins = $toMinutes($startTime);
        $breakStartMins = $enableBreak ? $toMinutes($breakStart) : null;
        $breakEndMins = $enableBreak ? $toMinutes($breakEnd) : null;
        $matchesInCurrentSlot = 0;

        // Build child-to-parent mapping and unscheduled matches across all tournaments
        $parents = [];
        $unscheduled = [];
        $scheduledTimes = []; // id => start_mins
        $tournamentNames = [];

        foreach ($tournaments as $tournament) {
            $matches = $tournament->matches()->get();
            $tournamentNames[$tournament->id] = $tournament->name ?? '';
            foreach ($matches as $m) {
                $unscheduled[$m->id] = $m;
                $parents[$m->id] = [];
            }
            foreach ($matches as $m) {
                $next = $m->next_match_id;
                $loserNext = $m->loser_next_match_id;
                if ($next && isset($parents[$next])) {
                    $parents[$next][] = $m->id;
                }
                if ($loserNext && isset($parents[$loserNext])) {
                    $parents[$loserNext][] = $m->id;
                }
            }
        }

        while (count($unscheduled) > 0) {
            // Apply break shift if needed
            if ($enableBreak && $breakStartMins !== null && $breakEndMins !== null) {
                $matchEnd = $currentMins + $duration;
                if ($currentMins < $breakEndMins && $matchEnd > $breakStartMins) {
                    $currentMins = $breakEndMins;
                    $matchesInCurrentSlot = 0;
                }
            }

            // Find eligible matches across all tournaments
            $eligible = [];
            foreach ($unscheduled as $id => $m) {
                $parentsScheduled = true;
                $maxParentReadyTime = 0;

                foreach ($parents[$id] as $parentId) {
                    if (isset($scheduledTimes[$parentId])) {
                        $parentReadyTime = $scheduledTimes[$parentId] + $duration + $restTime;
                        if ($parentReadyTime > $maxParentReadyTime) {
                            $maxParentReadyTime = $parentReadyTime;
                        }
                    } else {
                        $parentsScheduled = false;
                        break;
                    }
                }

                if ($parentsScheduled && $currentMins >= $maxParentReadyTime) {
                    $eligible[] = $m;
                }
            }

            if (count($eligible) > 0) {
                // Sort eligible matches
                usort($eligible, function($a, $b) use ($tournamentNames) {
                    if ($a->round !== $b->round) {
                        return $a->round <=> $b->round;
                    }

                    $bracketOrder = [
                        'winners' => 1,
                        'losers' => 2,
                        'grand_final' => 3,
                        'round_robin' => 4,
                    ];
                    $ap = $bracketOrder[$a->bracket] ?? 99;
                    $bp = $bracketOrder[$b->bracket] ?? 99;

                    if ($ap !== $bp) {
                        return $ap <=> $bp;
                    }

                    if ($a->match_order !== $b->match_order) {
                        return $a->match_order <=> $b->match_order;
                    }

                    return $a->tournament_id <=> $b->tournament_id;
                });

                // Pick the first one
                $chosen = $eligible[0];
                $chosenId = $chosen->id;

                // Schedule it
                $chosen->update([
                    'scheduled_time' => $toTimeStr($currentMins)
                ]);

                $scheduledTimes[$chosenId] = $currentMins;
                unset($unscheduled[$chosenId]);

                $matchesInCurrentSlot++;
                if ($matchesInCurrentSlot >= $courtCount) {
                    // Move current time forward and reset slot count
                    $currentMins += $duration + $restTime;
                    $matchesInCurrentSlot = 0;
                }
            } else {
                // Find the earliest time one of the ready matches becomes eligible
                $earliestTime = null;
                foreach ($unscheduled as $id => $m) {
                    $parentsScheduled = true;
                    $maxParentReadyTime = 0;
                    foreach ($parents[$id] as $parentId) {
                        if (isset($scheduledTimes[$parentId])) {
                            $parentReadyTime = $scheduledTimes[$parentId] + $duration + $restTime;
                            if ($parentReadyTime > $maxParentReadyTime) {
                                $maxParentReadyTime = $parentReadyTime;
                            }
                        } else {
                            $parentsScheduled = false;
                            break;
                        }
                    }

                    if ($parentsScheduled) {
                        if ($earliestTime === null || $maxParentReadyTime < $earliestTime) {
                            $earliestTime = $maxParentReadyTime;
                        }
                    }
                }

                if ($earliestTime !== null && $earliestTime > $currentMins) {
                    $currentMins = $earliestTime;
                } else {
                    // Fallback
                    $currentMins += 1;
                }
                $matchesInCurrentSlot = 0;
            }
        }
    }

    public static function assignCourtsDynamically($subFolderId, $excludeMatchId = null, $excludeAllBypassed = false)
    {
        $subFolder = TournamentSubFolder::find($subFolderId);
        if (!$subFolder) return;

        // Check if there are any tournaments in this subfolder still in 'setup' status
        $hasSetup = self::where('tournament_sub_folder_id', $subFolderId)->where('status', 'setup')->exists();

        // Check if any match in this subfolder has already started scoring (has winner_id or score)
        $tournaments = self::where('tournament_sub_folder_id', $subFolderId)->get();
        $tournamentIds = $tournaments->pluck('id');
        $hasStartedScoring = TournamentMatch::whereIn('tournament_id', $tournamentIds)
            ->where(function($q) {
                $q->whereNotNull('winner_id')
                  ->orWhereNotNull('team1_score')
                  ->orWhereNotNull('team2_score');
            })->exists();

        if ($hasSetup && !$hasStartedScoring) {
            // Clear any uncompleted match court assignments
            TournamentMatch::whereIn('tournament_id', $tournamentIds)
                ->whereNull('winner_id')
                ->update(['court_number' => null]);
            return;
        }

        $assignedCourts = $subFolder->assigned_courts;
        if (!is_array($assignedCourts) || empty($assignedCourts)) {
            // If no courts are assigned, clear any uncompleted match court assignments
            TournamentMatch::whereIn('tournament_id', $tournamentIds)
                ->whereNull('winner_id')
                ->update(['court_number' => null]);
            return;
        }

        // Clear any uncompleted match court assignments first to ensure correct priority allocation
        TournamentMatch::whereIn('tournament_id', $tournamentIds)
            ->whereNull('winner_id')
            ->update(['court_number' => null]);

        // Re-read matches after updates
        $matches = TournamentMatch::whereIn('tournament_id', $tournamentIds)->get();

        // Busy/occupied courts (matches that have a court number assigned but no winner yet)
        $occupiedCourts = $matches->whereNotNull('court_number')
            ->whereNull('winner_id')
            ->pluck('court_number')
            ->toArray();

        // Available free courts
        $freeCourts = array_values(array_diff($assignedCourts, $occupiedCourts));

        // Ready matches that do not have a court yet:
        // - team1_id and team2_id must be set
        // - winner_id must be null
        // - court_number must be null
        $readyMatches = $matches->filter(function ($m) use ($excludeMatchId, $excludeAllBypassed) {
            $eligible = $m->team1_id !== null
                && $m->team2_id !== null
                && $m->winner_id === null
                && $m->court_number === null
                && $m->id !== $excludeMatchId;

            if ($excludeAllBypassed) {
                return $eligible && $m->bypass_count === 0;
            }

            return $eligible;
        });

        if ($readyMatches->isEmpty()) return;

        // Sort ready matches chronologically (by scheduled_time, round, match_order, tournament_id)
        $sortedReadyMatches = $readyMatches->values()->all();
        usort($sortedReadyMatches, function ($a, $b) {
            // Prioritize matches bypassed exactly once (players given a second chance)
            $priorityA = ($a->bypass_count === 1) ? 1 : 0;
            $priorityB = ($b->bypass_count === 1) ? 1 : 0;
            if ($priorityA !== $priorityB) {
                return $priorityB <=> $priorityA; // 1 comes before 0
            }

            $timeA = $a->scheduled_time ?? '99:99';
            $timeB = $b->scheduled_time ?? '99:99';
            if ($timeA !== $timeB) {
                return $timeA <=> $timeB;
            }
            if ($a->round !== $b->round) {
                return $a->round <=> $b->round;
            }
            if ($a->match_order !== $b->match_order) {
                return $a->match_order <=> $b->match_order;
            }
            return $a->tournament_id <=> $b->tournament_id;
        });

        // Assign free courts to ready matches
        foreach ($sortedReadyMatches as $m) {
            if (empty($freeCourts)) {
                break;
            }
            $court = array_shift($freeCourts);
            $m->update(['court_number' => $court]);
        }
    }

    public static function assignCourtsDynamicallyForTournament($tournamentId, $excludeMatchId = null, $excludeAllBypassed = false)
    {
        $tournament = self::find($tournamentId);
        if (!$tournament || $tournament->tournament_sub_folder_id) return;

        // Check if the tournament is still in 'setup' status
        $hasSetup = $tournament->status === 'setup';

        // Check if any match in this tournament has already started scoring
        $hasStartedScoring = TournamentMatch::where('tournament_id', $tournamentId)
            ->where(function($q) {
                $q->whereNotNull('winner_id')
                  ->orWhereNotNull('team1_score')
                  ->orWhereNotNull('team2_score');
            })->exists();

        if ($hasSetup && !$hasStartedScoring) {
            // Clear any uncompleted match court assignments
            TournamentMatch::where('tournament_id', $tournamentId)
                ->whereNull('winner_id')
                ->update(['court_number' => null]);
            return;
        }

        $assignedCourts = $tournament->assigned_courts;
        if (!is_array($assignedCourts) || empty($assignedCourts)) {
            // If no courts are assigned, clear any uncompleted match court assignments
            TournamentMatch::where('tournament_id', $tournamentId)
                ->whereNull('winner_id')
                ->update(['court_number' => null]);
            return;
        }

        // Clear any uncompleted match court assignments first to ensure correct priority allocation
        TournamentMatch::where('tournament_id', $tournamentId)
            ->whereNull('winner_id')
            ->update(['court_number' => null]);

        // Re-read matches after updates
        $matches = TournamentMatch::where('tournament_id', $tournamentId)->get();

        // Busy/occupied courts (matches that have a court number assigned but no winner yet)
        $occupiedCourts = $matches->whereNotNull('court_number')
            ->whereNull('winner_id')
            ->pluck('court_number')
            ->toArray();

        // Available free courts
        $freeCourts = array_values(array_diff($assignedCourts, $occupiedCourts));

        // Ready matches that do not have a court yet
        $readyMatches = $matches->filter(function ($m) use ($excludeMatchId, $excludeAllBypassed) {
            $eligible = $m->team1_id !== null
                && $m->team2_id !== null
                && $m->winner_id === null
                && $m->court_number === null
                && $m->id !== $excludeMatchId;

            if ($excludeAllBypassed) {
                return $eligible && $m->bypass_count === 0;
            }

            return $eligible;
        });

        if ($readyMatches->isEmpty()) return;

        // Sort ready matches chronologically
        $sortedReadyMatches = $readyMatches->values()->all();
        usort($sortedReadyMatches, function ($a, $b) {
            $priorityA = ($a->bypass_count === 1) ? 1 : 0;
            $priorityB = ($b->bypass_count === 1) ? 1 : 0;
            if ($priorityA !== $priorityB) {
                return $priorityB <=> $priorityA;
            }

            $timeA = $a->scheduled_time ?? '99:99';
            $timeB = $b->scheduled_time ?? '99:99';
            if ($timeA !== $timeB) {
                return $timeA <=> $timeB;
            }
            if ($a->round !== $b->round) {
                return $a->round <=> $b->round;
            }
            if ($a->match_order !== $b->match_order) {
                return $a->match_order <=> $b->match_order;
            }
            return $a->tournament_id <=> $b->tournament_id;
        });

        // Assign free courts to ready matches
        foreach ($sortedReadyMatches as $m) {
            if (empty($freeCourts)) {
                break;
            }
            $court = array_shift($freeCourts);
            $m->update(['court_number' => $court]);
        }
    }
}
