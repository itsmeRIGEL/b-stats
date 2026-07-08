<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipPayment extends Model
{
    protected $fillable = [
        'player_id',
        'amount',
        'billing_period',
        'paid_at',
        'revoked_at',
        'venue_id',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'revoked_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }
}
