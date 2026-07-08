<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameMatch extends Model
{
    protected $fillable = [
        'player_1_id',
        'player_1_name',
        'player_2_id',
        'player_2_name',
        'player_3_id',
        'player_3_name',
        'player_4_id',
        'player_4_name',
        'player_1_score',
        'player_2_score',
        'match_date',
        'loss_points',
        'is_tallied',
        'is_walkin',
        'fee_amount',
        'walkin_fee_type',
        'booking_id',
        'venue_id',
        'submitted_by_user_id',
        'submitted_by_role',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function player1()
    {
        return $this->belongsTo(Player::class, 'player_1_id');
    }

    public function player2()
    {
        return $this->belongsTo(Player::class, 'player_2_id');
    }

    public function player3()
    {
        return $this->belongsTo(Player::class, 'player_3_id');
    }

    public function player4()
    {
        return $this->belongsTo(Player::class, 'player_4_id');
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
