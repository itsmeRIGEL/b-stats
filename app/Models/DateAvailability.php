<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DateAvailability extends Model
{
    protected $fillable = ['date', 'is_closed', 'opening_time', 'closing_time', 'close_reason', 'venue_id'];

    protected $casts = [
        'is_closed' => 'boolean',
    ];

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }
}
