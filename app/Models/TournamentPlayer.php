<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentPlayer extends Model
{
    protected $fillable = ['tournament_id', 'player1_name', 'player2_name', 'seed'];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
}
