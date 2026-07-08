<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentMatch extends Model
{
    protected $fillable = [
        'tournament_id', 'round', 'match_order', 'bracket',
        'team1_id', 'team2_id', 'team1_score', 'team2_score',
        'winner_id', 'next_match_id', 'next_match_slot',
        'loser_next_match_id', 'loser_next_match_slot',
        'scheduled_time', 'court_number', 'bypass_count', 'is_forfeited', 'venue_id',
    ];

    protected $casts = [
        'is_forfeited' => 'boolean',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function team1()
    {
        return $this->belongsTo(TournamentPlayer::class, 'team1_id');
    }

    public function team2()
    {
        return $this->belongsTo(TournamentPlayer::class, 'team2_id');
    }

    public function winner()
    {
        return $this->belongsTo(TournamentPlayer::class, 'winner_id');
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }
}
