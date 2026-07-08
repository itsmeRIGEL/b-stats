<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'full_name',
        'phone',
        'birthday',
        'address',
        'is_member',
        'membership_expires_at',
        'last_monthly_due_paid_at',
        'total_matches',
        'wins',
        'losses',
        'in_session',
        'show_in_roster',
        'venue_id',
    ];

    protected $casts = [
        'last_monthly_due_paid_at' => 'datetime',
        'membership_expires_at' => 'datetime',
    ];

    protected $appends = ['win_rate'];

    public function bookings()
    {
        return $this->belongsToMany(Booking::class)
            ->withPivot(['status', 'invited_by_user_id', 'responded_at'])
            ->withTimestamps();
    }

    public function matchesAsPlayer1()
    {
        return $this->hasMany(GameMatch::class, 'player_1_id');
    }

    public function matchesAsPlayer2()
    {
        return $this->hasMany(GameMatch::class, 'player_2_id');
    }
    public function walkIns()
    {
        return $this->hasMany(WalkIn::class);
    }

    public function getWinRateAttribute()
    {
        if (empty($this->total_matches) || $this->total_matches == 0) return 0;
        return round(($this->wins / $this->total_matches) * 100, 1);
    }

    public function membershipPayments()
    {
        return $this->hasMany(MembershipPayment::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}




