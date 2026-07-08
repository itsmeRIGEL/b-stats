<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DayAvailability extends Model
{
    protected $fillable = ['day_of_week', 'is_enabled', 'is_closed', 'opening_time', 'closing_time', 'close_reason', 'venue_id'];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_closed' => 'boolean',
    ];

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }
}
