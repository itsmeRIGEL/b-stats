<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentSubFolder extends Model
{
    protected $fillable = [
        'name', 'tournament_day_id', 'order', 'assigned_scorer_id',
        'start_time', 'match_duration', 'rest_time', 'enable_break', 'break_start', 'break_end',
        'assigned_courts', 'venue_id',
    ];

    protected $casts = [
        'enable_break' => 'boolean',
        'assigned_courts' => 'array',
    ];

    public function tournaments()
    {
        return $this->hasMany(Tournament::class, 'tournament_sub_folder_id');
    }

    public function tournamentDay()
    {
        return $this->belongsTo(TournamentDay::class);
    }

    public function assignedScorer()
    {
        return $this->belongsTo(User::class, 'assigned_scorer_id');
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }
}
