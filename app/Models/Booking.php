<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'booking_date',
        'start_time',
        'end_time',
        'cost_per_hour',
        'total_cost',
        'lead_name',
        'lead_address',
        'guest_email',
        'guest_phone',
        'player_count',
        'court_number',
        'client_type',
        'status',
        'cancelled_at',
        'approved_by',
        'approved_at',
        'payment_status',
        'receipt_photo',
        'scorer_id',
        'type',
        'venue_id',
        'scoring_state',
    ];

    protected $casts = [
        'scoring_state' => 'array',
    ];

    public function players()
    {
        return $this->belongsToMany(Player::class)
            ->withPivot(['status', 'invited_by_user_id', 'responded_at'])
            ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function matches()
    {
        return $this->hasMany(GameMatch::class, 'booking_id');
    }

    public function scorer()
    {
        return $this->belongsTo(User::class, 'scorer_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }
}






