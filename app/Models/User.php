<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'gender',
        'gender_other',
        'email',
        'password',
        'avatar',
        'facebook_url',
        'instagram_url',
        'website_url',
        'social_links',
        'all_time_stats_visible_fields',
        'role',
        'allow_unverified_access',
        'scheduler_id',
        'venue_id',
    ];

    public function isPlayer(): bool
    {
        return $this->getCachedRole() === 'player';
    }

    public function isAdmin(): bool
    {
        return $this->getCachedRole() === 'admin';
    }

    public function isScheduler(): bool
    {
        $role = $this->getCachedRole();
        if ($role === 'scheduler_scorer' && request()->hasSession()) {
            $activeRole = request()->session()->get('active_role');
            if ($activeRole) {
                return $activeRole === 'scheduler';
            }
        }
        return $role === 'scheduler' || $role === 'scheduler_scorer';
    }

    public function isScorer(): bool
    {
        $role = $this->getCachedRole();
        if ($role === 'scheduler_scorer' && request()->hasSession()) {
            $activeRole = request()->session()->get('active_role');
            if ($activeRole) {
                return $activeRole === 'scorer';
            }
        }
        return $role === 'scorer' || $role === 'scheduler_scorer';
    }

    public function scheduler()
    {
        return $this->belongsTo(User::class, 'scheduler_id');
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function playerProfiles()
    {
        return $this->hasMany(Player::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function venues()
    {
        return $this->hasMany(Venue::class, 'scheduler_id');
    }

    public function scorerUsers()
    {
        return $this->hasMany(User::class, 'scheduler_id');
    }

    public function currentVenue()
    {
        if (!Schema::hasTable('venues')) {
            return null;
        }

        if (!Schema::hasColumn('users', 'venue_id') || !Schema::hasColumn('users', 'scheduler_id')) {
            return null;
        }

        if ($this->venue) {
            return $this->venue;
        }

        if (in_array($this->role, ['scheduler', 'scheduler_scorer'], true)) {
            return $this->venues()->oldest('id')->first();
        }

        if ($this->role === 'player' && Schema::hasColumn('bookings', 'user_id')) {
            $booking = $this->bookings()
                ->whereNotNull('venue_id')
                ->where('status', 'approved')
                ->with('venue')
                ->orderByDesc('booking_date')
                ->orderByDesc('start_time')
                ->first();

            return $booking?->venue;
        }

        return null;
    }

    /**
     * Get cached role for faster authentication checks
     */
    private function getCachedRole(): string
    {
        return (string) ($this->role ?? 'admin');
    }

    public function homeRoute(): string
    {
        return match (true) {
            $this->isScheduler() && !$this->currentVenue() => route('venue-setup', absolute: false),
            $this->isScheduler() => route('bookings', absolute: false),
            $this->isScorer() => route('scoring', absolute: false),
            $this->isPlayer() => route('all-time-stats', absolute: false),
            default => route('dashboard', absolute: false),
        };
    }

    /**
     * Determine if the user has verified their email address,
     * or if they have explicit permission to bypass verification.
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->allow_unverified_access || ! is_null($this->email_verified_at);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'allow_unverified_access' => 'boolean',
            'all_time_stats_visible_fields' => 'array',
            'social_links' => 'array',
        ];
    }
}
