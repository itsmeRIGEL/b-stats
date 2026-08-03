<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentRequest extends Model
{
    protected $fillable = [
        'user_id',
        'venue_id',
        'name',
        'category',
        'preferred_date',
        'preferred_start_time',
        'notes',
        'receipt_photo',
        'total_cost',
        'payment_status',
        'request_type',
        'status',
        'rejection_reason',
        'approved_by_user_id',
        'approved_at',
        'tournament_id',
        'tournament_day_id',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function tournamentDay()
    {
        return $this->belongsTo(TournamentDay::class);
    }
}
