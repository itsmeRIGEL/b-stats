<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    protected $fillable = [
        'scheduler_id',
        'name',
        'address',
        'tagline',
        'description',
        'logo_path',
        'cover_photo_path',
        'gallery_paths',
        'contact_email',
        'contact_phone',
        'facebook_url',
        'amenities',
        'covered_court_count',
        'court_count',
        'is_active',
        'app_name',
        'opening_time',
        'closing_time',
        'default_hourly_rate',
        'member_booking_fee',
        'non_member_booking_fee',
        'membership_monthly_fee',
        'membership_yearly_fee',
        'walkin_member_fee',
        'walkin_non_member_fee',
        'walkin_ball_surcharge',
        'booking_expiration_grace_minutes',
        'allow_past_edits',
        'refund_full_hours',
        'refund_full_mins',
        'refund_full_pct',
        'refund_partial_hours',
        'refund_partial_mins',
        'refund_partial_pct',
        'refund_no_pct',
        'payment_account_name',
        'payment_qr_photo',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allow_past_edits' => 'boolean',
        'gallery_paths' => 'array',
        'amenities' => 'array',
    ];

    public function scheduler()
    {
        return $this->belongsTo(User::class, 'scheduler_id');
    }

    public function getRouteKeyName()
    {
        return 'name';
    }
}
