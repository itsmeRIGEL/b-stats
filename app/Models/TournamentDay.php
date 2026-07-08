<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentDay extends Model
{
    protected $fillable = [
        'name', 'date', 'status', 'assigned_courts', 'venue_id',
    ];

    protected $casts = [
        'date' => 'date',
        'assigned_courts' => 'array',
    ];

    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }

    public function subFolders()
    {
        return $this->hasMany(TournamentSubFolder::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }
}
