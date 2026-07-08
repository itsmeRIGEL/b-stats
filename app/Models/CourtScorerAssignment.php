<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtScorerAssignment extends Model
{
    protected $fillable = [
        'court_number',
        'scorer_id',
        'assignment_date',
        'venue_id',
    ];

    public function scorer()
    {
        return $this->belongsTo(User::class, 'scorer_id');
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }
}
