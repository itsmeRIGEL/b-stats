<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

use App\Models\Player;
use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\MembershipPayment;
use App\Models\User;
use Inertia\Inertia;

use App\Models\SystemSetting;
use App\Models\CourtScorerAssignment;
use App\Models\DayAvailability;
use App\Models\DateAvailability;
use App\Models\Venue;

class PickleballController extends Controller
{
    private const MEMBER_BOOKING_RATE = 180;
    private const NON_MEMBER_BOOKING_RATE = 200;
    private const WALKIN_MEMBER_FEE = 15;
    private const WALKIN_NON_MEMBER_FEE = 20;
    private const WALKIN_BALL_SURCHARGE = 5;

    private function activeVenueId(): ?int
    {
        $user = auth()->user();
        if (!$user || $user->role === 'admin') {
            return null;
        }
        if ($user->venue_id) {
            return $user->venue_id;
        }
        if ($user->role === 'player') {
            $player = Player::where('user_id', $user->id)->first();
            if ($player) {
                if ($player->venue_id) {
                    return $player->venue_id;
                }
                $now = now();
                $activeBooking = Booking::where('status', 'approved')
                    ->where('booking_date', $now->toDateString())
                    ->where('start_time', '<=', $now->toTimeString())
                    ->where('end_time', '>', $now->toTimeString())
                    ->where(function ($query) use ($user, $player) {
                        $query->whereHas('players', fn($q) => $q->where('players.id', $player->id)->where('booking_player.status', 'accepted'))
                            ->orWhere('user_id', $user->id)
                            ->orWhereJsonContains('scoring_state->localRegisteredPlayerIds', $player->id)
                            ->orWhereJsonContains('scoring_state->activePlayerIds', $player->id)
                            ->orWhereRaw('LOWER(lead_name) = ?', [strtolower($user->username ?? '')])
                            ->orWhereRaw('LOWER(lead_name) = ?', [strtolower($user->name ?? '')])
                            ->orWhereRaw('LOWER(lead_name) = ?', [strtolower($player->name ?? '')]);
                    })
                    ->first();
                if ($activeBooking) {
                    return $activeBooking->venue_id;
                }
            }
            return null;
        }
        $schedulerId = $user->role === 'scorer' ? $user->scheduler_id : $user->id;
        if (!$schedulerId) {
            return null;
        }
        $venue = Venue::where('scheduler_id', $schedulerId)->first();
        return $venue?->id;
    }

    private function activeVenue(): ?Venue
    {
        $user = auth()->user();
        if (!$user || $user->role === 'admin') {
            return null;
        }
        $schedulerId = $user->role === 'scorer' ? $user->scheduler_id : $user->id;
        if (!$schedulerId) {
            return null;
        }
        return Venue::where('scheduler_id', $schedulerId)->first();
    }

    private function venueSettings(): \Illuminate\Support\Collection
    {
        $venue = $this->activeVenue();
        $global = SystemSetting::all()->pluck('value', 'key');
        if (!$venue) {
            return $global;
        }

        $venueMap = [
            'opening_time' => $venue->opening_time,
            'closing_time' => $venue->closing_time,
            'court_count' => $venue->court_count,
            'default_hourly_rate' => $venue->default_hourly_rate,
            'member_booking_fee' => $venue->member_booking_fee,
            'non_member_booking_fee' => $venue->non_member_booking_fee,
            'membership_monthly_fee' => $venue->membership_monthly_fee,
            'membership_yearly_fee' => $venue->membership_yearly_fee,
            'walkin_member_fee' => $venue->walkin_member_fee,
            'walkin_non_member_fee' => $venue->walkin_non_member_fee,
            'walkin_ball_surcharge' => $venue->walkin_ball_surcharge,
            'booking_expiration_grace_minutes' => $venue->booking_expiration_grace_minutes,
            'allow_past_edits' => $venue->allow_past_edits,
            'app_name' => $venue->app_name,
        ];

        foreach ($venueMap as $key => $value) {
            if (!is_null($value)) {
                $global[$key] = $value;
            }
        }

        return $global;
    }

    public function dashboard()
    {
        $settings = $this->venueSettings();
        $venueId = $this->activeVenueId();
        $today = now()->toDateString();

        $todayBookingRev = Booking::where('type', 'booking')->where('payment_status', 'paid')->whereDate('booking_date', $today)->when($venueId, fn($q) => $q->where('venue_id', $venueId))->sum('total_cost');
        $todayReclubRev = Booking::where('type', 'reclub')->where('payment_status', 'paid')->whereDate('booking_date', $today)->when($venueId, fn($q) => $q->where('venue_id', $venueId))->sum('total_cost');
        $todayWalkinRev = GameMatch::where('is_walkin', true)->whereDate('match_date', $today)->when($venueId, fn($q) => $q->where('venue_id', $venueId))->sum('fee_amount');
        $todayMembershipRev = MembershipPayment::whereNull('revoked_at')->whereDate('paid_at', $today)->when($venueId, fn($q) => $q->where('venue_id', $venueId))->sum('amount');
        $todayTotalRev = $todayBookingRev + $todayReclubRev + $todayWalkinRev + $todayMembershipRev;

        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();
        $weeklyBookingRev = Booking::where('type', 'booking')->where('payment_status', 'paid')->whereBetween('booking_date', [$weekStart, $weekEnd])->when($venueId, fn($q) => $q->where('venue_id', $venueId))->sum('total_cost');
        $weeklyReclubRev = Booking::where('type', 'reclub')->where('payment_status', 'paid')->whereBetween('booking_date', [$weekStart, $weekEnd])->when($venueId, fn($q) => $q->where('venue_id', $venueId))->sum('total_cost');
        $weeklyWalkinRev = GameMatch::where('is_walkin', true)->whereBetween('match_date', [$weekStart, $weekEnd])->when($venueId, fn($q) => $q->where('venue_id', $venueId))->sum('fee_amount');
        $weeklyMembershipRev = MembershipPayment::whereNull('revoked_at')->whereBetween('paid_at', [$weekStart, $weekEnd])->when($venueId, fn($q) => $q->where('venue_id', $venueId))->sum('amount');

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $monthlyBookingRev = Booking::where('type', 'booking')->where('payment_status', 'paid')->whereBetween('booking_date', [$monthStart, $monthEnd])->when($venueId, fn($q) => $q->where('venue_id', $venueId))->sum('total_cost');
        $monthlyReclubRev = Booking::where('type', 'reclub')->where('payment_status', 'paid')->whereBetween('booking_date', [$monthStart, $monthEnd])->when($venueId, fn($q) => $q->where('venue_id', $venueId))->sum('total_cost');
        $monthlyWalkinRev = GameMatch::where('is_walkin', true)->whereBetween('match_date', [$monthStart, $monthEnd])->when($venueId, fn($q) => $q->where('venue_id', $venueId))->sum('fee_amount');
        $monthlyMembershipRev = MembershipPayment::whereNull('revoked_at')->whereBetween('paid_at', [$monthStart, $monthEnd])->when($venueId, fn($q) => $q->where('venue_id', $venueId))->sum('amount');

        return Inertia::render('Dashboard', [
            'total_players' => Player::when($venueId, fn($q) => $q->where('venue_id', $venueId))->count(),
            'active_members' => Player::where('is_member', true)->when($venueId, fn($q) => $q->where('venue_id', $venueId))->count(),
            'upcoming_bookings' => Booking::with('players')->where('booking_date', '>=', now()->toDateString())->when($venueId, fn($q) => $q->where('venue_id', $venueId))->get(),
            'top_players' => Player::when($venueId, fn($q) => $q->where('venue_id', $venueId))->orderBy('wins', 'desc')->take(5)->get(),
            'top_booking_players' => $this->computeTopPlayersByBookingType('booking'),
            'top_walkin_players' => $this->computeTopPlayersByBookingType('walk-in'),
            'top_reclub_players' => $this->computeTopPlayersByBookingType('reclub'),
            'weather' => $this->fetchWeather($settings->toArray()),
            'today_revenue' => [
                'total' => (float) $todayTotalRev,
                'bookings' => (float) $todayBookingRev,
                'reclub' => (float) $todayReclubRev,
                'walkins' => (float) $todayWalkinRev,
                'memberships' => (float) $todayMembershipRev,
            ],
            'weekly_revenue' => [
                'bookings' => (float) $weeklyBookingRev,
                'reclub' => (float) $weeklyReclubRev,
                'walkins' => (float) $weeklyWalkinRev,
                'memberships' => (float) $weeklyMembershipRev,
                'total' => (float) ($weeklyBookingRev + $weeklyReclubRev + $weeklyWalkinRev + $weeklyMembershipRev),
            ],
            'monthly_revenue' => [
                'bookings' => (float) $monthlyBookingRev,
                'reclub' => (float) $monthlyReclubRev,
                'walkins' => (float) $monthlyWalkinRev,
                'memberships' => (float) $monthlyMembershipRev,
                'total' => (float) ($monthlyBookingRev + $monthlyReclubRev + $monthlyWalkinRev + $monthlyMembershipRev),
            ],
        ]);
    }

    public function settings()
    {
        $this->cleanExpiredOverrides();
        $venueId = $this->activeVenueId();
        return Inertia::render('PickleballSettings', [
            'settings' => $this->venueSettings(),
            'weeklyAvailabilities' => DayAvailability::when($venueId, fn($q) => $q->where('venue_id', $venueId))->orderBy('day_of_week')->get(),
            'dateOverrides' => DateAvailability::when($venueId, fn($q) => $q->where('venue_id', $venueId))->orderBy('date')->get(),
        ]);
    }

    public function resolveAvailabilityForDate($date)
    {
        $this->cleanExpiredOverrides();
        $venueId = $this->activeVenueId();

        // 1. Check for specific date override
        $dateOverride = DateAvailability::where('date', $date);
        if ($venueId) {
            $dateOverride->where('venue_id', $venueId);
        }
        $dateOverride = $dateOverride->first();
        if ($dateOverride) {
            return [
                'is_closed' => (bool)$dateOverride->is_closed,
                'opening_time' => $dateOverride->opening_time ? substr($dateOverride->opening_time, 0, 5) : null,
                'closing_time' => $dateOverride->closing_time ? substr($dateOverride->closing_time, 0, 5) : null,
                'close_reason' => $dateOverride->close_reason,
            ];
        }

        // 2. Check for day-of-week setting
        $dayOfWeek = \Carbon\Carbon::parse($date)->dayOfWeek; // 0 = Sunday, 6 = Saturday
        $daySetting = DayAvailability::where('day_of_week', $dayOfWeek);
        if ($venueId) {
            $daySetting->where('venue_id', $venueId);
        }
        $daySetting = $daySetting->first();
        if ($daySetting && $daySetting->is_enabled) {
            return [
                'is_closed' => (bool)$daySetting->is_closed,
                'opening_time' => $daySetting->opening_time ? substr($daySetting->opening_time, 0, 5) : null,
                'closing_time' => $daySetting->closing_time ? substr($daySetting->closing_time, 0, 5) : null,
                'close_reason' => $daySetting->close_reason,
            ];
        }

        // 3. Fallback to venue or system settings
        $settings = $this->venueSettings();
        return [
            'is_closed' => false,
            'opening_time' => isset($settings['opening_time']) ? substr($settings['opening_time'], 0, 5) : '08:00',
            'closing_time' => isset($settings['closing_time']) ? substr($settings['closing_time'], 0, 5) : '22:00',
            'close_reason' => null,
        ];
    }

    private function validateBookingHours($startTimeStr, $endTimeStr, $avail)
    {
        if (!$avail['opening_time'] || !$avail['closing_time']) {
            return true;
        }

        list($sh, $sm) = explode(':', substr($startTimeStr, 0, 5));
        list($eh, $em) = explode(':', substr($endTimeStr, 0, 5));
        $sh = (int)$sh; $sm = (int)$sm;
        $eh = (int)$eh; $em = (int)$em;

        if ($eh < $sh || ($eh === $sh && $em <= $sm)) {
            $eh += 24;
        }

        list($oh, $om) = explode(':', $avail['opening_time']);
        list($ch, $cm) = explode(':', $avail['closing_time']);
        $oh = (int)$oh; $om = (int)$om;
        $ch = (int)$ch; $cm = (int)$cm;

        if ($ch < $oh || ($ch === $oh && $cm < $om)) {
            $ch += 24;
        } elseif ($ch === 0 && $cm === 0) {
            $ch = 24;
        }

        $startMinutes = $sh * 60 + $sm;
        $endMinutes = $eh * 60 + $em;
        $openMinutes = $oh * 60 + $om;
        $closeMinutes = $ch * 60 + $cm;

        if ($startMinutes < $openMinutes) {
            return 'start_time_before_open';
        }

        if ($endMinutes > $closeMinutes) {
            return 'end_time_after_close';
        }

        return true;
    }

    private function cleanExpiredOverrides()
    {
        DateAvailability::where('date', '<', now()->toDateString())->delete();
    }

    public function updateWeeklyAvailability(Request $request)
    {
        $validated = $request->validate([
            'schedules' => 'required|array',
            'schedules.*.day_of_week' => 'required|integer|min:0|max:6',
            'schedules.*.is_enabled' => 'required|boolean',
            'schedules.*.is_closed' => 'required|boolean',
            'schedules.*.opening_time' => 'nullable|string',
            'schedules.*.closing_time' => 'nullable|string',
            'schedules.*.close_reason' => 'nullable|string|max:255',
        ]);

        $venueId = $this->activeVenueId();

        foreach ($validated['schedules'] as $sched) {
            $attrs = ['day_of_week' => $sched['day_of_week']];
            if ($venueId) {
                $attrs['venue_id'] = $venueId;
            }
            DayAvailability::updateOrCreate(
                $attrs,
                [
                    'is_enabled' => $sched['is_enabled'],
                    'is_closed' => $sched['is_closed'],
                    'opening_time' => $sched['is_closed'] ? null : ($sched['opening_time'] ?? null),
                    'closing_time' => $sched['is_closed'] ? null : ($sched['closing_time'] ?? null),
                    'close_reason' => $sched['is_closed'] ? ($sched['close_reason'] ?? null) : null,
                ]
            );
        }

        return redirect()->back()->with('success', 'Weekly availability updated successfully.');
    }

    public function updateDateOverride(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'is_closed' => 'required|boolean',
            'opening_time' => 'nullable|string',
            'closing_time' => 'nullable|string',
            'close_reason' => 'nullable|string|max:255',
        ]);

        $venueId = $this->activeVenueId();
        $attrs = ['date' => $validated['date']];
        if ($venueId) {
            $attrs['venue_id'] = $venueId;
        }

        DateAvailability::updateOrCreate(
            $attrs,
            [
                'is_closed' => $validated['is_closed'],
                'opening_time' => $validated['is_closed'] ? null : ($validated['opening_time'] ?? null),
                'closing_time' => $validated['is_closed'] ? null : ($validated['closing_time'] ?? null),
                'close_reason' => $validated['is_closed'] ? ($validated['close_reason'] ?? null) : null,
            ]
        );

        return redirect()->back()->with('success', 'Date override saved successfully.');
    }

    public function deleteDateOverride(DateAvailability $override)
    {
        $override->delete();
        return redirect()->back()->with('success', 'Date override deleted successfully.');
    }

    public function adminUsers()
    {
        $currentUser = auth()->user();
        $isAdmin = $currentUser && $currentUser->isAdmin();

        $listableRoles = $isAdmin
            ? ['admin', 'scheduler', 'scorer', 'scheduler_scorer']
            : ['scheduler', 'scorer', 'scheduler_scorer'];

        $users = User::query()
            ->whereIn('role', $listableRoles)
            ->select('id', 'name', 'email', 'role', 'email_verified_at', 'allow_unverified_access')
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        $roleCounts = User::query()
            ->selectRaw('role, COUNT(*) as total')
            ->whereIn('role', ['scheduler', 'scorer', 'scheduler_scorer'])
            ->groupBy('role')
            ->pluck('total', 'role');

        $unverifiedTotal = User::query()
            ->whereIn('role', ['scheduler', 'scorer', 'scheduler_scorer'])
            ->whereNull('email_verified_at')
            ->count();

        $schedulers = (int) ($roleCounts['scheduler'] ?? 0);
        $scorers = (int) ($roleCounts['scorer'] ?? 0);
        $combined = (int) ($roleCounts['scheduler_scorer'] ?? 0);

        return Inertia::render('AdminUsers', [
            'scheduler_total' => $schedulers + $combined,
            'scorer_total' => $scorers + $combined,
            'total_users' => $schedulers + $scorers + $combined,
            'unverified_total' => $unverifiedTotal,
            'is_admin' => $isAdmin,
            'users' => $users,
        ]);
    }

    public function adminUpdateUser(Request $request, User $user)
    {
        $currentUser = auth()->user();
        $isAdmin = $currentUser && $currentUser->isAdmin();

        if (!$isAdmin && $user->role === 'admin') {
            abort(403, 'Only admins can edit admin accounts.');
        }

        if (!$isAdmin) {
            if ($user->scheduler_id !== $currentUser->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        $allowedRoles = $isAdmin
            ? ['admin', 'scheduler', 'scorer', 'scheduler_scorer']
            : ['scorer'];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in($allowedRoles)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'allow_unverified_access' => ['nullable', 'boolean'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        $user->allow_unverified_access = (bool) ($validated['allow_unverified_access'] ?? false);

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return back()->with('success', 'User updated successfully.');
    }

    public function adminStoreUser(Request $request)
    {
        $currentUser = auth()->user();
        $isAdmin = $currentUser && $currentUser->isAdmin();

        $allowedRoles = $isAdmin
            ? ['admin', 'scheduler', 'scorer', 'scheduler_scorer']
            : ['scorer'];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in($allowedRoles)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'allow_unverified_access' => ['nullable', 'boolean'],
        ]);

        $extra = [];
        if (!$isAdmin) {
            $extra['scheduler_id'] = $currentUser->id;
            $extra['venue_id'] = $this->activeVenueId() ?? $currentUser->venue_id;
        }

        $user = User::create(array_merge([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
            'allow_unverified_access' => (bool) ($validated['allow_unverified_access'] ?? false),
        ], $extra));

        event(new Registered($user));

        return back()->with('success', 'User created successfully. A verification email has been sent.');
    }

    public function adminDestroyUser(User $user)
    {
        $currentUser = auth()->user();
        $isAdmin = $currentUser && $currentUser->isAdmin();

        if (!$isAdmin && $user->role === 'admin') {
            abort(403, 'Only admins can delete admin accounts.');
        }

        if (!$isAdmin) {
            if ($user->scheduler_id !== $currentUser->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        $user->delete();

        return back()->with('success', 'User deleted successfully.');
    }

    public function updateSettings(Request $request)
    {
        $data = $request->all();

        $currentUser = auth()->user();
        if ($currentUser && ($currentUser->role === 'scheduler' || $currentUser->role === 'scheduler_scorer')) {
            // Schedulers can update operational settings, refund policy, leaderboard, branding, and booking keys
            $allowedKeys = [
                'opening_time', 'closing_time', 'court_count', 'booking_expiration_grace_minutes', 'allow_past_edits',
                'refund_full_hours', 'refund_full_mins', 'refund_full_pct', 'refund_partial_hours', 'refund_partial_mins', 'refund_partial_pct', 'refund_no_pct',
                'scoring_win_points', 'scoring_loss_penalty', 'scoring_randomize_loss',
                'app_name', 'app_logo',
                'default_hourly_rate', 'member_booking_fee', 'non_member_booking_fee', 'membership_monthly_fee', 'membership_yearly_fee', 'walkin_member_fee', 'walkin_non_member_fee', 'walkin_ball_surcharge',
                'payment_account_name', 'payment_qr_photo',
            ];
            $data = array_intersect_key($data, array_flip($allowedKeys));
        }

        if ($request->hasFile('app_logo')) {
            $path = $request->file('app_logo')->store('logos', 'public');
            $data['app_logo'] = '/storage/' . $path;
        } else {
            unset($data['app_logo']);
        }

        if ($request->hasFile('payment_qr_photo')) {
            $path = $request->file('payment_qr_photo')->store('payment-qrs', 'public');
            $data['payment_qr_photo'] = '/storage/' . $path;
        } else {
            unset($data['payment_qr_photo']);
        }

        foreach ($data as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        // Also persist payment reference to the scheduler's venue
        if ($currentUser && ($currentUser->role === 'scheduler' || $currentUser->role === 'scheduler_scorer')) {
            $venue = Venue::where('scheduler_id', $currentUser->id)->first();
            if ($venue) {
                $venueData = [];
                if (isset($data['payment_account_name'])) {
                    $venueData['payment_account_name'] = $data['payment_account_name'];
                }
                if ($request->hasFile('payment_qr_photo')) {
                    $venuePath = $request->file('payment_qr_photo')->store('venue-payment-qrs', 'public');
                    $venueData['payment_qr_photo'] = '/storage/' . $venuePath;
                }
                if (!empty($venueData)) {
                    $venue->update($venueData);
                }

                // Sync operational and branding settings to venue
                $syncKeys = [
                    'court_count', 'opening_time', 'closing_time', 'default_hourly_rate',
                    'member_booking_fee', 'non_member_booking_fee', 'membership_monthly_fee', 'membership_yearly_fee',
                    'walkin_member_fee', 'walkin_non_member_fee', 'walkin_ball_surcharge',
                    'booking_expiration_grace_minutes', 'allow_past_edits',
                    'refund_full_hours', 'refund_full_mins', 'refund_full_pct',
                    'refund_partial_hours', 'refund_partial_mins', 'refund_partial_pct', 'refund_no_pct',
                    'app_name',
                ];
                $venueSync = [];
                foreach ($syncKeys as $key) {
                    if (isset($data[$key])) {
                        $venueSync[$key] = $data[$key];
                    }
                }
                if (isset($venueSync['court_count'])) {
                    $newCourtCount = (int) $venueSync['court_count'];
                    if ($venue->covered_court_count && (int) $venue->covered_court_count > $newCourtCount) {
                        $venueSync['covered_court_count'] = $newCourtCount;
                    }
                }
                if (!empty($venueSync)) {
                    $venue->update($venueSync);
                }
            }
        }

        return redirect()->back()->with('success', 'Settings updated successfully.');
    }

    public function bookings()
    {
        $settings = $this->venueSettings();
        $venueId = $this->activeVenueId();
        $today = now()->toDateString();

        $courtAssignmentsQuery = CourtScorerAssignment::where('assignment_date', $today)
            ->with('scorer:id,name');
        if ($venueId) {
            $courtAssignmentsQuery->where('venue_id', $venueId);
        }
        $courtAssignments = $courtAssignmentsQuery->get()
            ->keyBy('court_number')
            ->map(fn($a) => ['scorer_id' => $a->scorer_id, 'scorer_name' => $a->scorer?->name]);

        $courtCount = (int) ($settings['court_count'] ?? 4);
        $allCourtNumbers = range(1, $courtCount);
        $missingCourts = array_diff($allCourtNumbers, $courtAssignments->keys()->toArray());

        if (!empty($missingCourts)) {
            $fallbackQuery = CourtScorerAssignment::whereIn('court_number', $missingCourts)
                ->where('scorer_id', '!=', null)
                ->orderByDesc('assignment_date')
                ->with('scorer:id,name');
            if ($venueId) {
                $fallbackQuery->where('venue_id', $venueId);
            }
            $fallbacks = $fallbackQuery->get()->unique('court_number');

            foreach ($fallbacks as $fb) {
                $courtAssignments[$fb->court_number] = [
                    'scorer_id' => $fb->scorer_id,
                    'scorer_name' => $fb->scorer?->name,
                ];
            }
        }

        $this->cleanExpiredOverrides();
        $bookingsQuery = Booking::with(['players' => fn($q) => $q->with('user'), 'scorer', 'approver:id,name'])->orderBy('booking_date', 'desc');
        if ($venueId) {
            $bookingsQuery->where('venue_id', $venueId);
        }

        $playersQuery = Player::with('user');
        if ($venueId) {
            $playersQuery->where('venue_id', $venueId);
        }

        $user = auth()->user();
        $scorersQuery = \App\Models\User::whereIn('role', ['scorer', 'scheduler_scorer'])->select('id', 'name');
        if ($user && ($user->role === 'scheduler' || $user->role === 'scheduler_scorer')) {
            $scorersQuery->where('scheduler_id', $user->id);
        } elseif ($venueId) {
            $scorersQuery->where('venue_id', $venueId);
        }

        $bookings = $bookingsQuery->get()->map(function ($booking) {
            $owner = $booking->players->first(fn($p) => !$p->pivot->invited_by_user_id);
            if ($owner?->user) {
                $booking->lead_name = $owner->user->username ?? $owner->user->name;
            }
            return $booking;
        });

        return Inertia::render('Bookings', [
            'bookings' => $bookings,
            'players' => $playersQuery->get(),
            'scorers' => $scorersQuery->get(),
            'courtAssignments' => $courtAssignments,
            'settings' => $settings,
            'weather'  => $this->fetchWeather($settings->toArray()),
            'weeklyAvailabilities' => DayAvailability::when($venueId, fn($q) => $q->where('venue_id', $venueId))->orderBy('day_of_week')->get(),
            'dateOverrides' => DateAvailability::when($venueId, fn($q) => $q->where('venue_id', $venueId))->orderBy('date')->get(),
        ]);
    }

    public function saveCourtAssignment(Request $request)
    {
        $user = auth()->user();
        $scorerRule = 'nullable|exists:users,id';
        if ($user && ($user->role === 'scheduler' || $user->role === 'scheduler_scorer')) {
            $scorerRule = [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) use ($user) {
                    $query->where('scheduler_id', $user->id);
                })
            ];
        }

        $validated = $request->validate([
            'court_number' => 'required|integer|min:1',
            'scorer_id' => $scorerRule,
            'assignment_date' => 'required|date',
        ]);

        $venueId = $this->activeVenueId();
        $attrs = [
            'court_number' => $validated['court_number'],
            'assignment_date' => $validated['assignment_date'],
        ];
        if ($venueId) {
            $attrs['venue_id'] = $venueId;
        }

        CourtScorerAssignment::updateOrCreate(
            $attrs,
            ['scorer_id' => $validated['scorer_id']]
        );

        return redirect()->back()->with('success', 'Court assignment saved.');
    }

    private function fetchWeather(array $settings): array
    {
        $lat = $settings['latitude'] ?? '14.5995';
        $lon = $settings['longitude'] ?? '120.9842';
        $cacheKey = 'weather_' . $lat . '_' . $lon . '_' . now()->format('Y-m-d-H');

        return Cache::remember($cacheKey, 3600, function () use ($lat, $lon) {
            try {
                $response = Http::timeout(6)->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude'      => $lat,
                    'longitude'     => $lon,
                    'daily'         => 'weathercode,temperature_2m_max,temperature_2m_min',
                    'current'       => 'temperature_2m,relative_humidity_2m,apparent_temperature,weathercode,wind_speed_10m',
                    'hourly'        => 'temperature_2m,precipitation_probability,weathercode',
                    'timezone'      => 'auto',
                    'past_days'     => 31,
                    'forecast_days' => 16,
                ]);

                if (!$response->ok()) {
                    return $this->getMockWeather();
                }

                $data = $response->json();
                $weather = [];

                foreach ($data['daily']['time'] as $i => $date) {
                    $weather[$date] = [
                        'code'     => $data['daily']['weathercode'][$i],
                        'temp_max' => (int) round($data['daily']['temperature_2m_max'][$i]),
                        'temp_min' => (int) round($data['daily']['temperature_2m_min'][$i]),
                    ];
                }

                $weather['__current'] = [
                    'time' => $data['current']['time'] ?? now()->toIso8601String(),
                    'temperature' => isset($data['current']['temperature_2m']) ? (int) round($data['current']['temperature_2m']) : null,
                    'feels_like' => isset($data['current']['apparent_temperature']) ? (int) round($data['current']['apparent_temperature']) : null,
                    'humidity' => isset($data['current']['relative_humidity_2m']) ? (int) $data['current']['relative_humidity_2m'] : null,
                    'wind_kph' => isset($data['current']['wind_speed_10m']) ? (int) round($data['current']['wind_speed_10m']) : null,
                    'code' => $data['current']['weathercode'] ?? null,
                ];

                $weather['__hourly'] = [];
                $hourlyTimes = $data['hourly']['time'] ?? [];
                $hourlyTemps = $data['hourly']['temperature_2m'] ?? [];
                $hourlyCodes = $data['hourly']['weathercode'] ?? [];
                $hourlyRain = $data['hourly']['precipitation_probability'] ?? [];
                $nowTs = now()->timestamp;
                foreach ($hourlyTimes as $i => $hourTime) {
                    $hourTs = strtotime($hourTime);
                    if ($hourTs === false || $hourTs < $nowTs) {
                        continue;
                    }
                    $weather['__hourly'][] = [
                        'time' => $hourTime,
                        'temperature' => isset($hourlyTemps[$i]) ? (int) round($hourlyTemps[$i]) : null,
                        'code' => $hourlyCodes[$i] ?? null,
                        'rain_chance' => isset($hourlyRain[$i]) ? (int) $hourlyRain[$i] : null,
                    ];

                    if (count($weather['__hourly']) >= 6) {
                        break;
                    }
                }

                if (empty($weather)) {
                    return $this->getMockWeather();
                }

                return $weather;
            } catch (\Exception $e) {
                return $this->getMockWeather();
            }
        });
    }

    private function getMockWeather(): array
    {
        $weather = [];
        $start = now()->subDays(31);
        for ($i = 0; $i < 60; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $weather[$date] = [
                'code' => rand(0, 3), // Clear to partly cloudy
                'temp_max' => rand(28, 34),
                'temp_min' => rand(22, 26),
            ];
        }

        $weather['__current'] = [
            'time' => now()->toIso8601String(),
            'temperature' => rand(27, 33),
            'feels_like' => rand(29, 36),
            'humidity' => rand(60, 88),
            'wind_kph' => rand(5, 20),
            'code' => rand(0, 3),
        ];

        $weather['__hourly'] = [];
        for ($h = 0; $h < 6; $h++) {
            $weather['__hourly'][] = [
                'time' => now()->addHours($h)->toIso8601String(),
                'temperature' => rand(27, 33),
                'code' => rand(0, 3),
                'rain_chance' => rand(0, 40),
            ];
        }

        return $weather;
    }

    public function storeBooking(Request $request)
    {
        $validated = $this->validateBooking($request);

        $avail = $this->resolveAvailabilityForDate($validated['booking_date']);
        if ($avail['is_closed']) {
            return redirect()->back()->withErrors(['booking_date' => 'This date is marked as closed.']);
        }

        $start = \Carbon\Carbon::parse($validated['start_time']);
        $durationHours = (float) $validated['duration_hours'];
        $end = (clone $start)->addHours($durationHours);

        $startTimeStr = $start->toTimeString();
        $endTimeStr = $end->toTimeString();

        $timeCheck = $this->validateBookingHours($startTimeStr, $endTimeStr, $avail);
        if ($timeCheck === 'start_time_before_open') {
            return redirect()->back()->withErrors(['start_time' => 'The selected start time is before the opening time for this date (' . $avail['opening_time'] . ').']);
        }
        if ($timeCheck === 'end_time_after_close') {
            return redirect()->back()->withErrors(['duration_hours' => 'The selected end time is after the closing time for this date (' . $avail['closing_time'] . ').']);
        }

        $settings = $this->venueSettings();
        $rate = $validated['client_type'] === 'member'
            ? (float) ($settings['member_booking_fee'] ?? 180)
            : (float) ($settings['non_member_booking_fee'] ?? 200);

        // Check for overlap on the same court and date (excluding rejected bookings)
        $overlapQuery = Booking::where('booking_date', $validated['booking_date'])
            ->where('court_number', $validated['court_number'])
            ->where('status', '!=', 'rejected')
            ->where(function ($query) use ($startTimeStr, $endTimeStr) {
                $query->where('start_time', '<', $endTimeStr)
                      ->where('end_time', '>', $startTimeStr);
            });
        $venueId = $this->activeVenueId();
        if ($venueId) {
            $overlapQuery->where('venue_id', $venueId);
        }

        if ($overlapQuery->exists()) {
            return redirect()->back()->withErrors(['start_time' => 'This court is already scheduled/booked during this time.']);
        }

        $total_cost = $durationHours * $rate;

        $receiptPath = null;
        if ($request->hasFile('receipt_photo')) {
            $receiptPath = $request->file('receipt_photo')->store('receipts', 'public');
        }

        $booking = Booking::create([
            'booking_date' => $validated['booking_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $end->toTimeString(),
            'cost_per_hour' => $rate,
            'total_cost' => $total_cost,
            'lead_name' => $validated['lead_name'],
            'lead_address' => $validated['lead_address'],
            'guest_phone' => $validated['guest_phone'] ?? null,
            'player_count' => $validated['player_count'],
            'court_number' => $validated['court_number'],
            'client_type' => $validated['client_type'],
            'receipt_photo' => $receiptPath,
            'scorer_id' => $validated['scorer_id'] ?? null,
            'type' => $validated['type'],
            'venue_id' => $venueId,
        ]);

        if (!empty($validated['player_ids'])) {
            $booking->players()->attach($validated['player_ids']);
        }

        return redirect()->back()->with('success', 'Booking created successfully.');
    }

    public function updateBooking(Request $request, Booking $booking)
    {
        $validated = $this->validateBooking($request);

        $avail = $this->resolveAvailabilityForDate($validated['booking_date']);
        if ($avail['is_closed']) {
            return redirect()->back()->withErrors(['booking_date' => 'This date is marked as closed.']);
        }

        $start = \Carbon\Carbon::parse($validated['start_time']);
        $durationHours = (float) $validated['duration_hours'];
        $end = (clone $start)->addHours($durationHours);

        $startTimeStr = $start->toTimeString();
        $endTimeStr = $end->toTimeString();

        $timeCheck = $this->validateBookingHours($startTimeStr, $endTimeStr, $avail);
        if ($timeCheck === 'start_time_before_open') {
            return redirect()->back()->withErrors(['start_time' => 'The selected start time is before the opening time for this date (' . $avail['opening_time'] . ').']);
        }
        if ($timeCheck === 'end_time_after_close') {
            return redirect()->back()->withErrors(['duration_hours' => 'The selected end time is after the closing time for this date (' . $avail['closing_time'] . ').']);
        }

        $settings = $this->venueSettings();
        $rate = $validated['client_type'] === 'member'
            ? (float) ($settings['member_booking_fee'] ?? 180)
            : (float) ($settings['non_member_booking_fee'] ?? 200);

        // Check for overlap on the same court and date (excluding rejected bookings and this booking)
        $overlapQuery = Booking::where('booking_date', $validated['booking_date'])
            ->where('court_number', $validated['court_number'])
            ->where('status', '!=', 'rejected')
            ->where('id', '!=', $booking->id)
            ->where(function ($query) use ($startTimeStr, $endTimeStr) {
                $query->where('start_time', '<', $endTimeStr)
                      ->where('end_time', '>', $startTimeStr);
            });
        $venueId = $this->activeVenueId();
        if ($venueId) {
            $overlapQuery->where('venue_id', $venueId);
        }

        if ($overlapQuery->exists()) {
            return redirect()->back()->withErrors(['start_time' => 'This court is already scheduled/booked during this time.']);
        }

        $total_cost = $durationHours * $rate;

        $receiptPath = $booking->receipt_photo;
        if ($request->hasFile('receipt_photo')) {
            if ($receiptPath) Storage::disk('public')->delete($receiptPath);
            $receiptPath = $request->file('receipt_photo')->store('receipts', 'public');
        }

        $booking->update([
            'booking_date' => $validated['booking_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $end->toTimeString(),
            'cost_per_hour' => $rate,
            'total_cost' => $total_cost,
            'lead_name' => $validated['lead_name'],
            'lead_address' => $validated['lead_address'],
            'guest_phone' => $validated['guest_phone'] ?? null,
            'player_count' => $validated['player_count'],
            'court_number' => $validated['court_number'],
            'client_type' => $validated['client_type'],
            'receipt_photo' => $receiptPath,
            'scorer_id' => $validated['scorer_id'] ?? null,
            'type' => $validated['type'],
        ]);

        if (isset($validated['player_ids'])) {
            $booking->players()->sync($validated['player_ids']);
        }

        return redirect()->back()->with('success', 'Booking updated successfully.');
    }

    protected function validateBooking(Request $request)
    {
        $user = auth()->user();
        $scorerRule = 'nullable|exists:users,id';
        if ($user && ($user->role === 'scheduler' || $user->role === 'scheduler_scorer')) {
            $scorerRule = [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) use ($user) {
                    $query->where('scheduler_id', $user->id);
                })
            ];
        }

        return $request->validate([
            'booking_date' => 'required|date',
            'start_time' => 'required',
            'duration_hours' => 'required|numeric|min:0.25',
            'cost_per_hour' => 'required|numeric',
            'lead_name' => 'required|string|max:255',
            'lead_address' => 'nullable|string|max:255',
            'guest_phone' => 'nullable|string|max:255',
            'player_count' => 'required|integer|min:1',
            'court_number' => 'required|integer|min:1',
            'client_type' => ['required', Rule::in(['member', 'non_member'])],
            'player_ids' => 'nullable|array',
            'receipt_photo' => 'nullable|image|max:5120',
            'scorer_id' => $scorerRule,
            'type' => 'required|string|in:booking,walk-in,reclub',
        ]);
    }

    public function destroyBooking(Booking $booking)
    {
        $venueId = $this->activeVenueId();
        if ($venueId && $booking->venue_id && $booking->venue_id !== $venueId) {
            abort(403, 'This booking belongs to a different venue.');
        }

        $booking->players()->detach();
        $booking->delete();

        return redirect()->back()->with('success', 'Booking deleted successfully.');
    }

    public function memberships()
    {
        $venueId = $this->activeVenueId();

        $query = Player::with('user');

        if ($venueId) {
            $playedPlayerIds = GameMatch::where('venue_id', $venueId)
                ->pluck('player_1_id')
                ->merge(GameMatch::where('venue_id', $venueId)->pluck('player_2_id'))
                ->filter()
                ->unique()
                ->all();

            $paymentPlayerIds = MembershipPayment::where('venue_id', $venueId)->pluck('player_id')->filter()->unique()->all();

            $bookedUserIds = Booking::where('venue_id', $venueId)->pluck('user_id')->filter()->all();

            $bookingPlayerUserIds = \Illuminate\Support\Facades\DB::table('booking_player')
                ->join('bookings', 'booking_player.booking_id', '=', 'bookings.id')
                ->join('players', 'booking_player.player_id', '=', 'players.id')
                ->where('bookings.venue_id', $venueId)
                ->pluck('players.user_id')
                ->filter()
                ->all();

            $requestUserIds = \App\Models\TournamentRequest::where('venue_id', $venueId)->pluck('user_id')->filter()->all();
            $primaryVenueUserIds = User::where('venue_id', $venueId)->pluck('id')->filter()->all();

            $relevantUserIds = array_values(array_unique(array_merge($bookedUserIds, $bookingPlayerUserIds, $requestUserIds, $primaryVenueUserIds)));

            $query->where(function ($q) use ($venueId, $playedPlayerIds, $paymentPlayerIds, $relevantUserIds) {
                $q->where('venue_id', $venueId)
                  ->orWhereIn('id', $playedPlayerIds)
                  ->orWhereIn('id', $paymentPlayerIds);

                if (!empty($relevantUserIds)) {
                    $q->orWhereIn('user_id', $relevantUserIds);
                }
            });
        }

        return Inertia::render('Memberships', [
            'players' => $query->get(),
            'settings' => $this->venueSettings(),
        ]);
    }

    public function toggleMembership(Player $player)
    {
        $venueId = $this->activeVenueId();
        if ($venueId && $player->venue_id && $player->venue_id !== $venueId) {
            abort(403, 'This player belongs to a different venue.');
        }

        $settings = $this->venueSettings();
        $yearlyFee = (float) ($settings['membership_yearly_fee'] ?? 50);
        $isBecomingMember = !$player->is_member;

        $player->update([
            'is_member' => $isBecomingMember,
            'membership_expires_at' => $isBecomingMember ? now()->addYear() : null,
        ]);

        if ($isBecomingMember && $yearlyFee > 0) {
            \App\Models\MembershipPayment::create([
                'player_id' => $player->id,
                'amount' => $yearlyFee,
                'billing_period' => 'yearly',
                'paid_at' => now(),
                'venue_id' => $venueId,
            ]);
        }

        // If revoking within 24 hours of the most recent yearly payment, mark it as revoked
        if (!$isBecomingMember) {
            $recentPayment = \App\Models\MembershipPayment::where('player_id', $player->id)
                ->where('billing_period', 'yearly')
                ->whereNull('revoked_at')
                ->latest('paid_at')
                ->first();

            if ($recentPayment && $recentPayment->paid_at->diffInHours(now()) <= 24) {
                $recentPayment->update(['revoked_at' => now()]);
            }
        }

        return redirect()->back();
    }

    public function payMonthlyDue(Player $player)
    {
        $venueId = $this->activeVenueId();
        if ($venueId && $player->venue_id && $player->venue_id !== $venueId) {
            abort(403, 'This player belongs to a different venue.');
        }

        if (!$player->is_member) {
            return redirect()->back()->with('error', 'Player is not an active member.');
        }

        $settings = $this->venueSettings();
        $monthlyFee = (float) ($settings['membership_monthly_fee'] ?? 15);

        if ($monthlyFee > 0) {
            \App\Models\MembershipPayment::create([
                'player_id' => $player->id,
                'amount' => $monthlyFee,
                'billing_period' => 'monthly',
                'paid_at' => now(),
                'venue_id' => $venueId,
            ]);

            $player->update(['last_monthly_due_paid_at' => now()]);
        }

        return redirect()->back();
    }

    public function revokeMonthlyDue(Player $player)
    {
        $venueId = $this->activeVenueId();
        if ($venueId && $player->venue_id && $player->venue_id !== $venueId) {
            abort(403, 'This player belongs to a different venue.');
        }

        if (!$player->is_member) {
            return redirect()->back()->with('error', 'Player is not an active member.');
        }

        // Mark the most recent monthly payment as revoked within 24 hours
        $recentPayment = \App\Models\MembershipPayment::where('player_id', $player->id)
            ->where('billing_period', 'monthly')
            ->whereNull('revoked_at')
            ->latest('paid_at')
            ->first();

        if ($recentPayment && $recentPayment->paid_at->diffInHours(now()) <= 24) {
            $recentPayment->update(['revoked_at' => now()]);
        }

        $player->update(['last_monthly_due_paid_at' => null]);

        return redirect()->back();
    }

    public function scoring()
    {
        $user = auth()->user();
        $settings = $this->venueSettings();
        $venueId = $this->activeVenueId();

        // Player access gate: require an active booking or accepted invitation
        $playerBooking = null;
        $playerScoringBlocked = false;
        $playerScoringNotice = null;
        $bookingRoster = null;
        $activeBooking = null;

        if ($user->isPlayer()) {
            $player = Player::where('user_id', $user->id)->first();
            if (!$player) {
                $playerScoringBlocked = true;
                $playerScoringNotice = 'No player profile found. Please book a venue first to access scoring.';
            } else {
                $now = now();
                $activeBooking = Booking::where('status', 'approved')
                    ->where('booking_date', $now->toDateString())
                    ->where('start_time', '<=', $now->toTimeString())
                    ->where('end_time', '>', $now->toTimeString())
                    ->where(function ($query) use ($user, $player) {
                        $query->where('user_id', $user->id)
                            ->orWhereHas('players', fn($q) => $q->where('players.id', $player->id)->where('booking_player.status', 'accepted'));
                    })
                    ->first();

                $pendingBooking = null;
                if (!$activeBooking) {
                    $pendingBooking = Booking::where('status', 'approved')
                        ->where('booking_date', $now->toDateString())
                        ->where('start_time', '<=', $now->toTimeString())
                        ->where('end_time', '>', $now->toTimeString())
                        ->whereHas('players', fn($q) => $q->where('players.id', $player->id)->where('booking_player.status', 'pending'))
                        ->first();
                }

                if ($activeBooking) {
                    // Auto-attach booking creator if missing from pivot
                    if ((int)$activeBooking->user_id === (int)$user->id && !$activeBooking->players()->where('players.id', $player->id)->exists()) {
                        $activeBooking->players()->attach($player->id, ['status' => 'accepted']);
                    }

                    $isInvited = (int)$activeBooking->user_id !== (int)$user->id;

                    $playerBooking = [
                        'id' => $activeBooking->id,
                        'user_id' => $activeBooking->user_id,
                        'court_number' => $activeBooking->court_number,
                        'venue_name' => $activeBooking->venue?->name,
                        'start_time' => $activeBooking->start_time,
                        'end_time' => $activeBooking->end_time,
                        'lead_name' => $activeBooking->lead_name,
                        'access_mode' => $isInvited ? 'view' : 'edit',
                    ];

                    $settings['player_scoring_mode'] = true;
                    $settings['player_scoring_view_only'] = $isInvited;

                    $bookingRoster = $activeBooking->players()
                        ->withPivot(['status', 'invited_by_user_id', 'responded_at'])
                        ->get()
                        ->map(fn($p) => [
                            'id' => $p->id,
                            'name' => $p->user?->username ?? $p->name,
                            'status' => $p->pivot->status ?? 'accepted',
                            'user_id' => $p->user_id,
                            'responded_at' => $p->pivot->responded_at,
                        ]);
                } else if ($pendingBooking) {
                    $playerScoringBlocked = true;
                    $playerScoringNotice = "You have a pending invitation to play at " . ($pendingBooking->venue?->name ?? 'Venue') . " (Court " . $pendingBooking->court_number . "). Please accept your invitation in notifications to access scoring.";
                } else {
                    $playerScoringBlocked = true;
                    $playerScoringNotice = "You don't have an active booking or invitation for this session. Book a venue first to access scoring.";
                }
            }
        }

        if ($user->isScorer()) {
            if (!$venueId) {
                $playerScoringBlocked = true;
                $playerScoringNotice = 'No active scheduling session found for this scorer. Please contact your scheduler to assign you to a venue.';
            }
        }

        // Get assigned courts for scorer users via court_scorer_assignments (with fallback to most recent assignments)
        $assignedCourts = [];
        if ($user && $user->isScorer()) {
            $today = now()->toDateString();
            
            // Get today's explicit assignments
            $assignmentsTodayQuery = CourtScorerAssignment::where('assignment_date', $today);
            if ($venueId) {
                $assignmentsTodayQuery->where('venue_id', $venueId);
            }
            $assignmentsToday = $assignmentsTodayQuery->get()
                ->keyBy('court_number');
                
            $courtCount = (int) ($settings['court_count'] ?? 4);
            $allCourtNumbers = range(1, $courtCount);
            
            // For any court lacking an assignment today, fall back to its most recent assignment
            $missingCourts = array_diff($allCourtNumbers, $assignmentsToday->keys()->toArray());
            if (!empty($missingCourts)) {
                $fallbackQuery = CourtScorerAssignment::whereIn('court_number', $missingCourts)
                    ->whereNotNull('scorer_id')
                    ->orderByDesc('assignment_date');
                if ($venueId) {
                    $fallbackQuery->where('venue_id', $venueId);
                }
                $fallbacks = $fallbackQuery->get()
                    ->unique('court_number')
                    ->keyBy('court_number');
                
                foreach ($fallbacks as $courtNumber => $fb) {
                    $assignmentsToday[$courtNumber] = $fb;
                }
            }
            
            // Filter courts assigned to this logged-in scorer
            $assignedCourtsList = $assignmentsToday
                ->filter(fn($a) => $a->scorer_id == $user->id)
                ->keys()
                ->toArray();

            // Merge with bookings today where scorer_id is the current scorer
            $bookingCourtsToday = Booking::where('booking_date', now()->toDateString())
                ->where('status', 'approved')
                ->where('scorer_id', $user->id)
                ->pluck('court_number')
                ->toArray();

            $assignedCourts = array_values(array_unique(array_merge($assignedCourtsList, $bookingCourtsToday)));
        }

        $effectiveVenueId = $venueId;
        if ($user && $user->isPlayer() && $activeBooking && $activeBooking->venue_id) {
            $effectiveVenueId = $activeBooking->venue_id;
        }

        $sessionPlayersQuery = Player::with('user')->where('in_session', true);
        if ($effectiveVenueId) {
            $sessionPlayersQuery->where('venue_id', $effectiveVenueId);
        }
        $players = $sessionPlayersQuery->get()->map(function ($player) use ($effectiveVenueId) {
            $player->name = $player->user?->username ?? $player->name;
            $untalliedQuery = GameMatch::where('is_tallied', false);
            if ($effectiveVenueId) {
                $untalliedQuery->where('venue_id', $effectiveVenueId);
            }
            $matchesAsP1 = (clone $untalliedQuery)->where('player_1_id', $player->id)->get();
            $matchesAsP2 = (clone $untalliedQuery)->where('player_2_id', $player->id)->get();
            $matchesAsP3 = (clone $untalliedQuery)->where('player_3_id', $player->id)->get();
            $matchesAsP4 = (clone $untalliedQuery)->where('player_4_id', $player->id)->get();

            $todayMatches = collect()->merge($matchesAsP1)->merge($matchesAsP2)->merge($matchesAsP3)->merge($matchesAsP4);

            $wins = 0;
            $losses = 0;

            foreach ($todayMatches as $match) {
                $isTeam1 = ($match->player_1_id === $player->id || $match->player_3_id === $player->id);
                $isTeam2 = ($match->player_2_id === $player->id || $match->player_4_id === $player->id);

                if ($isTeam1) {
                    if ($match->player_1_score > $match->player_2_score) $wins++;
                    else if ($match->player_1_score < $match->player_2_score) $losses++;
                } else if ($isTeam2) {
                    if ($match->player_2_score > $match->player_1_score) $wins++;
                    else if ($match->player_2_score < $match->player_1_score) $losses++;
                }
            }

            $total = $wins + $losses;
            $winRate = $total > 0 ? round(($wins / $total) * 100, 1) : 0;

            // Override the standard properties for the frontend
            $player->wins = $wins;
            $player->losses = $losses;
            $player->total_matches = $total;
            $player->win_rate = $winRate;
            
            return $player;
        })->sortByDesc(function ($player) {
            return $player->wins * 1000 + $player->win_rate; // Sort by wins, then win rate
        })->values();

        $now = now();
        $graceMinutes = (int) ($settings['booking_expiration_grace_minutes'] ?? 10);
        $graceTime = now()->subMinutes($graceMinutes)->toTimeString();

        // Get all approved bookings for today that have started, including expired ones
        $todayBookingsQuery = Booking::where('booking_date', $now->toDateString())
            ->where('start_time', '<=', $now->toTimeString())
            ->where('status', 'approved');
        if ($effectiveVenueId) {
            $todayBookingsQuery->where('venue_id', $effectiveVenueId);
        }
        $allTodayBookings = $todayBookingsQuery->get(['id', 'court_number', 'type', 'start_time', 'end_time', 'lead_name']);

        // Resolve usernames for bookings via booking_player → player → user
        $bookingIds = $allTodayBookings->pluck('id');
        $leadNameMap = [];
        if ($bookingIds->isNotEmpty()) {
            $bookingUsernames = \DB::table('booking_player')
                ->join('players', 'players.id', '=', 'booking_player.player_id')
                ->leftJoin('users', 'users.id', '=', 'players.user_id')
                ->whereIn('booking_player.booking_id', $bookingIds)
                ->whereNotNull('users.username')
                ->select('booking_player.booking_id', 'users.username')
                ->get()
                ->groupBy('booking_id');
            foreach ($bookingUsernames as $bId => $rows) {
                $leadNameMap[$bId] = $rows->first()->username;
            }
        }

        // Auto-add players from active bookings to the session roster (only accepted)
        $activeBookingIds = $allTodayBookings->pluck('id');
        if ($activeBookingIds->isNotEmpty()) {
            $bookingPlayerIds = \DB::table('booking_player')
                ->whereIn('booking_id', $activeBookingIds)
                ->where('status', 'accepted')
                ->pluck('player_id')
                ->unique();

            if ($bookingPlayerIds->isNotEmpty()) {
                $playerUpdateQuery = Player::whereIn('id', $bookingPlayerIds)->where('in_session', false);
                if ($effectiveVenueId) {
                    $playerUpdateQuery->where('venue_id', $effectiveVenueId);
                }
                $playerUpdateQuery->update(['in_session' => true]);
            }
        }

        // Mark bookings past the grace period as expired so the frontend can lock the queue
        $activeBookings = $allTodayBookings->map(function ($booking) use ($graceTime, $leadNameMap) {
            $isMidnightCrossing = $booking->end_time < $booking->start_time;
            $expired = !$isMidnightCrossing && $booking->end_time < $graceTime;
            return [
                'id' => $booking->id,
                'court_number' => $booking->court_number,
                'type' => $booking->type,
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
                'lead_name' => $leadNameMap[$booking->id] ?? $booking->lead_name,
                'expired' => $expired,
            ];
        })->keyBy('court_number');

        $winPoints = max(1, (int) ($settings['scoring_win_points'] ?? 10));
        $lossPenalty = max(1, (int) ($settings['scoring_loss_penalty'] ?? 5));

        $untalliedMatchesQuery = GameMatch::with(['player1', 'player2', 'player3', 'player4', 'booking'])
                        ->where('is_tallied', false)
                        ->orderBy('created_at', 'desc');
        if ($effectiveVenueId) {
            $untalliedMatchesQuery->where('venue_id', $effectiveVenueId);
        }

        return Inertia::render('Scoring', [
            'matches' => $untalliedMatchesQuery->get(),
            'players' => $players,
            'allPlayers' => $this->getAllPlayersForScoring($effectiveVenueId),
            'settings' => [
                'court_count' => $settings['court_count'] ?? '1',
                'walkin_courts' => $settings['walkin_courts'] ?? '',
                'both_courts' => $settings['both_courts'] ?? '',
                'booking_expiration_grace_minutes' => $settings['booking_expiration_grace_minutes'] ?? '10',
                'scoring_win_points' => $winPoints,
                'scoring_loss_penalty' => $lossPenalty,
                'walkin_member_fee' => (float) ($settings['walkin_member_fee'] ?? self::WALKIN_MEMBER_FEE),
                'walkin_non_member_fee' => (float) ($settings['walkin_non_member_fee'] ?? self::WALKIN_NON_MEMBER_FEE),
                'walkin_ball_surcharge' => (float) ($settings['walkin_ball_surcharge'] ?? self::WALKIN_BALL_SURCHARGE),
                'player_scoring_mode' => $settings['player_scoring_mode'] ?? false,
                'player_scoring_view_only' => $settings['player_scoring_view_only'] ?? false,
                'player_scoring_blocked' => $playerScoringBlocked,
            ],
            'assignedCourts' => $assignedCourts,
            'activeBookings' => $activeBookings,
            'playerBooking' => $playerBooking,
            'playerScoringBlocked' => $playerScoringBlocked,
            'playerScoringNotice' => $playerScoringNotice,
            'bookingRoster' => $bookingRoster,
            'scoringState' => $activeBooking ? $activeBooking->scoring_state : null,
        ]);
    }

    public function inviteBookingPlayers(Request $request)
    {
        $user = auth()->user();
        $player = Player::where('user_id', $user->id)->first();
        if (!$player) abort(403, 'No player profile found.');

        $now = now();
        $venueId = $this->activeVenueId();

        $booking = Booking::where('status', 'approved')
            ->where('booking_date', $now->toDateString())
            ->where('start_time', '<=', $now->toTimeString())
            ->where('end_time', '>', $now->toTimeString())
            ->when($venueId, fn($q) => $q->where('venue_id', $venueId))
            ->whereHas('players', fn($q) => $q->where('players.id', $player->id)->where('booking_player.status', 'accepted'))
            ->first();

        if (!$booking) abort(403, 'No active booking found.');

        $playerIds = $request->input('player_ids', []);
        foreach ($playerIds as $pid) {
            if (!$booking->players()->where('players.id', $pid)->exists()) {
                $booking->players()->attach($pid, [
                    'status' => 'pending',
                    'invited_by_user_id' => $user->id,
                ]);
            }
        }

        return redirect()->back();
    }

    public function respondToBookingInvitation(Request $request, Booking $booking)
    {
        $user = auth()->user();
        $player = Player::where('user_id', $user->id)->first();
        if (!$player) abort(403, 'No player profile found.');

        $statusValue = $request->input('status') ?? $request->input('response');
        $request->merge(['status' => $statusValue]);

        $validated = $request->validate([
            'status' => 'required|in:accepted,declined',
        ]);

        $booking->players()->updateExistingPivot($player->id, [
            'status' => $validated['status'],
            'responded_at' => now(),
        ]);

        return redirect()->back();
    }

    private function getAllPlayersForScoring($venueId = null)
    {
        // Auto-create missing Player records for registered player users
        \App\Models\User::where('role', 'player')->get()->each(function ($u) {
            Player::firstOrCreate(
                ['user_id' => $u->id],
                ['name' => $u->name, 'full_name' => $u->name, 'show_in_roster' => true]
            );
        });

        $allPlayersQuery = Player::with('user')->select('id', 'name', 'is_member', 'user_id', 'venue_id');
        if ($venueId) {
            $allPlayersQuery->where(function ($q) use ($venueId) {
                $q->where('venue_id', $venueId)
                  ->orWhereNull('venue_id')
                  ->orWhereNotNull('user_id');
            });
        }

        return $allPlayersQuery->get()->map(function ($p) {
            $displayName = ($p->user?->username && trim($p->user->username) !== '') ? $p->user->username : $p->name;
            return [
                'id' => $p->id,
                'name' => $displayName,
                'is_member' => (bool) $p->is_member,
            ];
        })->unique('id')->values();
    }

    private function getAllTimeStatsData($venueId = null)
    {
        $settings = $this->venueSettings();
        $winPoints = max(1, (int) ($settings['scoring_win_points'] ?? 10));

        $allPlayersQuery = Player::with('user');
        if ($venueId) {
            $allPlayersQuery->where(function ($q) use ($venueId) {
                $q->where('venue_id', $venueId)
                  ->orWhereExists(function ($subQuery) use ($venueId) {
                      $subQuery->select(\DB::raw(1))
                          ->from('game_matches')
                          ->where('venue_id', $venueId)
                          ->where('is_tallied', true)
                          ->where(function ($matchQ) {
                              $matchQ->whereColumn('game_matches.player_1_id', 'players.id')
                                     ->orWhereColumn('game_matches.player_2_id', 'players.id')
                                     ->orWhereColumn('game_matches.player_3_id', 'players.id')
                                     ->orWhereColumn('game_matches.player_4_id', 'players.id');
                          });
                  });
            });
        }
        $allPlayers = $allPlayersQuery->get();

        $baseMatchQuery = GameMatch::where('is_tallied', true);
        if ($venueId) {
            $baseMatchQuery->where('venue_id', $venueId);
        }
        $matches = (clone $baseMatchQuery)->get();

        $buildStats = function ($players, $matchesQuery) use ($winPoints) {
            return $players->map(function ($player) use ($winPoints, $matchesQuery) {
                $playerMatches = $matchesQuery->filter(function ($m) use ($player) {
                    return $m->player_1_id === $player->id ||
                           $m->player_2_id === $player->id ||
                           $m->player_3_id === $player->id ||
                           $m->player_4_id === $player->id;
                });

                $wins = 0;
                $losses = 0;
                foreach ($playerMatches as $match) {
                    $isTeam1 = ($match->player_1_id === $player->id || $match->player_3_id === $player->id);
                    if ($isTeam1) {
                        if ($match->player_1_score > $match->player_2_score) $wins++;
                        else if ($match->player_1_score < $match->player_2_score) $losses++;
                    } else {
                        if ($match->player_2_score > $match->player_1_score) $wins++;
                        else if ($match->player_2_score < $match->player_1_score) $losses++;
                    }
                }

                $total = $wins + $losses;
                $winRate = $total > 0 ? round(($wins / $total) * 100, 1) : 0;

                $lossPoints = $playerMatches->filter(function ($match) use ($player) {
                    $isTeam1 = ($match->player_1_id === $player->id || $match->player_3_id === $player->id);
                    if ($isTeam1) {
                        return $match->player_1_score < $match->player_2_score;
                    } else {
                        return $match->player_2_score < $match->player_1_score;
                    }
                })->sum('loss_points');

                $points = ($wins * $winPoints) - (int) $lossPoints;

                // Sync back to player model properties for compatibility
                $player->wins = $wins;
                $player->losses = $losses;
                $player->total_matches = $total;
                $player->win_rate = $winRate;

                // Profile visibility setup
                $user = $player->user;
                $availableSections = ['stats'];
                $profileDetails = [
                    'username' => null,
                    'first_name' => null,
                    'middle_name' => null,
                    'last_name' => null,
                    'suffix' => null,
                    'gender' => null,
                    'facebook_url' => null,
                    'instagram_url' => null,
                    'website_url' => null,
                    'social_links' => null,
                ];
                $contactDetails = [
                    'birthday' => null,
                    'address' => null,
                ];
                if ($user) {
                    $availableSections[] = 'profile';
                    $availableSections[] = 'membership';
                    $allFields = ['first_name', 'middle_name', 'last_name', 'suffix', 'gender', 'username', 'birthday', 'address', 'facebook_url', 'instagram_url', 'website_url', 'social_links'];
                    $visibleFields = $user->all_time_stats_visible_fields !== null ? $user->all_time_stats_visible_fields : $allFields;
                    if (in_array('username', $visibleFields, true)) {
                        $profileDetails['username'] = $user->username;
                    }
                    if (in_array('first_name', $visibleFields, true)) {
                        $profileDetails['first_name'] = $user->first_name;
                    }
                    if (in_array('middle_name', $visibleFields, true)) {
                        $profileDetails['middle_name'] = $user->middle_name;
                    }
                    if (in_array('last_name', $visibleFields, true)) {
                        $profileDetails['last_name'] = $user->last_name;
                    }
                    if (in_array('suffix', $visibleFields, true)) {
                        $profileDetails['suffix'] = $user->suffix;
                    }
                    if (in_array('gender', $visibleFields, true)) {
                        $profileDetails['gender'] = $user->gender;
                    }
                    if (in_array('facebook_url', $visibleFields, true)) {
                        $profileDetails['facebook_url'] = $user->facebook_url;
                    }
                    if (in_array('instagram_url', $visibleFields, true)) {
                        $profileDetails['instagram_url'] = $user->instagram_url;
                    }
                    if (in_array('website_url', $visibleFields, true)) {
                        $profileDetails['website_url'] = $user->website_url;
                    }
                    $profileDetails['social_links'] = $user->social_links;
                    if (in_array('birthday', $visibleFields, true)) {
                        $contactDetails['birthday'] = $user->birthday;
                    }
                    if (in_array('address', $visibleFields, true)) {
                        $contactDetails['address'] = $user->address;
                    }
                }

                return [
                    'id' => $player->id,
                    'name' => $user->username ?? $player->name,
                    'total_matches' => $total,
                    'wins' => $wins,
                    'losses' => $losses,
                    'win_rate' => $winRate,
                    'points' => $points,
                    'available_sections' => $availableSections,
                    'profile_details' => $profileDetails,
                    'contact_details' => $contactDetails,
                ];
            })->filter(fn($p) => $p['total_matches'] > 0)->values();
        };

        $players = $buildStats($allPlayers, $matches);

        $matchHistory = $matches
            ->sortByDesc('match_date')
            ->sortByDesc('created_at')
            ->map(function ($match) {
                $team1Score = $match->player_1_score;
                $team2Score = $match->player_2_score;
                $team1Won = $team1Score > $team2Score;

                return [
                    'id' => $match->id,
                    'match_date' => $match->match_date,
                    'created_at' => $match->created_at ? $match->created_at->toIso8601String() : null,
                    'updated_at' => $match->updated_at ? $match->updated_at->toIso8601String() : null,
                    'is_walkin' => $match->is_walkin,
                    'fee_amount' => $match->fee_amount,
                    'booking_id' => $match->booking_id,
                    'booking_type' => $match->booking?->type ?? ($match->is_walkin ? 'walk-in' : null),
                    'booking_time' => $match->booking ? date('g:i A', strtotime($match->booking->start_time)) . ' - ' . date('g:i A', strtotime($match->booking->end_time)) : null,
                    'booking_lead' => $match->booking?->lead_name,
                    'team1' => [
                        'players' => array_values(array_filter([
                            $match->player1?->user?->username ?? $match->player1?->name,
                            $match->player3?->user?->username ?? $match->player3?->name,
                        ])),
                        'player_ids' => array_values(array_filter([$match->player_1_id, $match->player_3_id])),
                        'score' => $team1Score,
                        'won' => $team1Won,
                    ],
                    'team2' => [
                        'players' => array_values(array_filter([
                            $match->player2?->user?->username ?? $match->player2?->name,
                            $match->player4?->user?->username ?? $match->player4?->name,
                        ])),
                        'player_ids' => array_values(array_filter([$match->player_2_id, $match->player_4_id])),
                        'score' => $team2Score,
                        'won' => !$team1Won,
                    ],
                ];
            })->values();

        return [
            'players' => $players,
            'matches' => $matchHistory,
        ];
    }    public function allTimeStats(Request $request)
    {
        $settings = $this->venueSettings();
        $winPoints = max(1, (int) ($settings['scoring_win_points'] ?? 10));
        
        $defaultVenueId = $this->activeVenueId();
        $selectedVenueId = $request->input('venue_id');
        if ($selectedVenueId === null) {
            $selectedVenueId = $defaultVenueId ? (string) $defaultVenueId : 'overall';
        }

        $venueIdForQuery = ($selectedVenueId === 'overall') ? null : (int) $selectedVenueId;
        $statsData = $this->getAllTimeStatsData($venueIdForQuery);

        $venues = Venue::where('is_active', true)->get()->map(fn($v) => [
            'id' => $v->id,
            'name' => $v->name,
        ]);

        $venueLabel = null;
        if ($selectedVenueId !== 'overall') {
            $venueRecord = Venue::find($selectedVenueId);
            $venueLabel = $venueRecord ? $venueRecord->name : null;
        }

        return Inertia::render('AllTimeStats', [
            'players' => $statsData['players'],
            'matches' => $statsData['matches'],
            'settings' => [
                'scoring_win_points' => $winPoints,
                'scoring_loss_penalty' => max(1, (int) ($settings['scoring_loss_penalty'] ?? 5)),
            ],
            'venueLabel' => $venueLabel,
            'venues' => $venues,
            'selectedVenueId' => $selectedVenueId,
        ]);
    }

    public function storeMatch(Request $request)
    {
        $user = auth()->user();
        if ($user && $user->isPlayer()) {
            $player = Player::where('user_id', $user->id)->first();
            if (!$player) {
                abort(403, 'Unauthorized.');
            }
            $bookingId = $request->input('booking_id');
            if (!$bookingId) {
                abort(403, 'Booking ID is required.');
            }
            $booking = Booking::find($bookingId);
            if (!$booking) {
                abort(403, 'Booking not found.');
            }
            $isOwner = $booking->players()
                ->where('players.id', $player->id)
                ->where('booking_player.status', 'accepted')
                ->whereNull('booking_player.invited_by_user_id')
                ->exists();
            if (!$isOwner) {
                abort(403, 'Invited players only have view-only access.');
            }
        }

        $validated = $request->validate([
            'player_1_id' => 'nullable|required_without:player_1_name|exists:players,id',
            'player_1_name' => 'nullable|string|max:255',
            'player_2_id' => 'nullable|required_without:player_2_name|exists:players,id',
            'player_2_name' => 'nullable|string|max:255',
            'player_3_id' => 'nullable|exists:players,id',
            'player_3_name' => 'nullable|string|max:255',
            'player_4_id' => 'nullable|exists:players,id',
            'player_4_name' => 'nullable|string|max:255',
            'player_1_score' => 'required|integer',
            'player_2_score' => 'required|integer|different:player_1_score',
            'match_date' => 'required|date',
            'is_walkin' => 'nullable|boolean',
            'walkin_fee_type' => ['nullable', Rule::in(['with_ball', 'without_ball'])],
            'booking_id' => 'nullable|integer|exists:bookings,id',
        ]);

        $settings = $this->venueSettings();
        $venueId = $this->activeVenueId();
        if ($request->input('booking_id')) {
            $booking = Booking::find($request->input('booking_id'));
            if ($booking) {
                $venueId = $booking->venue_id;
            }
        }
        $feeType = $request->boolean('is_walkin') ? ($validated['walkin_fee_type'] ?? 'with_ball') : null;

        $fee = 0.00;
        if ($request->boolean('is_walkin')) {
            $memberFee = (float) ($settings['walkin_member_fee'] ?? self::WALKIN_MEMBER_FEE);
            $nonMemberFee = (float) ($settings['walkin_non_member_fee'] ?? self::WALKIN_NON_MEMBER_FEE);
            $ballSurcharge = (float) ($settings['walkin_ball_surcharge'] ?? self::WALKIN_BALL_SURCHARGE);
            $hasBall = $feeType !== 'without_ball';

            $playerIds = array_filter([
                $validated['player_1_id'] ?? null,
                $validated['player_2_id'] ?? null,
                $validated['player_3_id'] ?? null,
                $validated['player_4_id'] ?? null,
            ]);
            $players = Player::whereIn('id', $playerIds)->get();

            $fee = 0.00;
            foreach ($players as $player) {
                $baseFee = $player->is_member ? $memberFee : $nonMemberFee;
                $fee += $baseFee + ($hasBall ? 0 : $ballSurcharge);
            }
        }

        $match = GameMatch::create(array_merge($validated, [
            'is_walkin' => $request->boolean('is_walkin'),
            'fee_amount' => $fee,
            'walkin_fee_type' => $feeType,
            'venue_id' => $venueId,
        ]));

        return redirect()->back();
    }

    public function updateMatch(Request $request, GameMatch $match)
    {
        $user = auth()->user();
        if ($user && $user->isPlayer()) {
            $player = Player::where('user_id', $user->id)->first();
            if (!$player) {
                abort(403, 'Unauthorized.');
            }
            $bookingId = $match->booking_id ?? $request->input('booking_id');
            if (!$bookingId) {
                abort(403, 'Booking ID is required.');
            }
            $booking = Booking::find($bookingId);
            if (!$booking || !$booking->players()
                ->where('players.id', $player->id)
                ->where('booking_player.status', 'accepted')
                ->whereNull('booking_player.invited_by_user_id')
                ->exists()) {
                abort(403, 'Unauthorized.');
            }
        }

        $validated = $request->validate([
            'player_1_id' => 'nullable|required_without:player_1_name|exists:players,id',
            'player_1_name' => 'nullable|string|max:255',
            'player_2_id' => 'nullable|required_without:player_2_name|exists:players,id',
            'player_2_name' => 'nullable|string|max:255',
            'player_3_id' => 'nullable|exists:players,id',
            'player_3_name' => 'nullable|string|max:255',
            'player_4_id' => 'nullable|exists:players,id',
            'player_4_name' => 'nullable|string|max:255',
            'player_1_score' => 'required|integer',
            'player_2_score' => 'required|integer|different:player_1_score',
            'match_date' => 'required|date',
            'is_walkin' => 'nullable|boolean',
            'walkin_fee_type' => ['nullable', Rule::in(['with_ball', 'without_ball'])],
            'booking_id' => 'nullable|integer|exists:bookings,id',
        ]);

        $settings = $this->venueSettings();
        $feeType = $request->boolean('is_walkin') ? ($validated['walkin_fee_type'] ?? 'with_ball') : null;

        $fee = 0.00;
        if ($request->boolean('is_walkin')) {
            $memberFee = (float) ($settings['walkin_member_fee'] ?? self::WALKIN_MEMBER_FEE);
            $nonMemberFee = (float) ($settings['walkin_non_member_fee'] ?? self::WALKIN_NON_MEMBER_FEE);
            $ballSurcharge = (float) ($settings['walkin_ball_surcharge'] ?? self::WALKIN_BALL_SURCHARGE);
            $hasBall = $feeType !== 'without_ball';

            $playerIds = array_filter([
                $validated['player_1_id'] ?? null,
                $validated['player_2_id'] ?? null,
                $validated['player_3_id'] ?? null,
                $validated['player_4_id'] ?? null,
            ]);
            $players = Player::whereIn('id', $playerIds)->get();

            $fee = 0.00;
            foreach ($players as $player) {
                $baseFee = $player->is_member ? $memberFee : $nonMemberFee;
                $fee += $baseFee + ($hasBall ? 0 : $ballSurcharge);
            }
        }

        $match->update(array_merge($validated, [
            'is_walkin' => $request->boolean('is_walkin'),
            'fee_amount' => $fee,
            'walkin_fee_type' => $feeType,
        ]));

        return redirect()->back()->with('success', 'Match result updated.');
    }

    public function togglePaymentStatus(Booking $booking)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        $booking->update([
            'payment_status' => $booking->payment_status === 'paid' ? 'unpaid' : 'paid',
        ]);

        return redirect()->back()->with('success', 'Payment status updated.');
    }

    public function storePlayer(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'full_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'birthday' => 'nullable|date',
            'address' => 'nullable|string|max:255',
        ]);

        $fromMembership = (bool) $request->input('from_membership', false);
        $venueId = $this->activeVenueId();

        $player = Player::whereRaw('LOWER(name) = ?', [strtolower($validated['name'])])->first();
        
        if ($player) {
            $update = [
                'phone' => $validated['phone'] ?? $player->phone,
                'birthday' => $validated['birthday'] ?? $player->birthday,
                'address' => $validated['address'] ?? $player->address,
            ];
            if ($fromMembership) {
                $update['show_in_roster'] = true;
            }
            if (!$fromMembership) {
                $update['in_session'] = true;
            }
            if ($venueId && !$player->venue_id) {
                $update['venue_id'] = $venueId;
            }
            $player->update($update);
        } else {
            Player::create([
                'name' => $validated['name'],
                'full_name' => $validated['full_name'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'birthday' => $validated['birthday'] ?? null,
                'address' => $validated['address'] ?? null,
                'in_session' => !$fromMembership,
                'show_in_roster' => $fromMembership,
                'venue_id' => $venueId,
            ]);
        }

        if ($request->header('X-Inertia')) {
            return redirect()->back();
        }

        return response()->json(['success' => true, 'message' => 'Player saved.']);
    }

    public function bulkAddToSession(Request $request)
    {
        $validated = $request->validate([
            'names' => 'required|array|min:1',
            'names.*' => 'required|string|max:255',
        ]);

        $venueId = $this->activeVenueId();

        foreach ($validated['names'] as $name) {
            $player = Player::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
            if (!$player) {
                return redirect()->back()->withErrors(['names' => "Player '{$name}' is not registered."]);
            }
            $player->update(['in_session' => true]);
            if ($venueId && !$player->venue_id) {
                $player->update(['venue_id' => $venueId]);
            }
        }

        $sessionPlayersQuery = Player::where('in_session', true);
        if ($venueId) {
            $sessionPlayersQuery->where('venue_id', $venueId);
        }

        $sessionPlayersQuery->with('user');
        $sessionPlayers = $sessionPlayersQuery->get()->map(fn($p) => tap($p, fn($p) => $p->name = ($p->user?->username && trim($p->user->username) !== '') ? $p->user->username : $p->name));

        return Inertia::render('Scoring', [
            'players' => $sessionPlayers,
            'allPlayers' => $this->getAllPlayersForScoring($venueId),
        ]);
    }

    public function resetSession()
    {
        $venueId = $this->activeVenueId();

        // Delete untallied matches recorded today
        $untalliedQuery = GameMatch::where('is_tallied', false);
        if ($venueId) {
            $untalliedQuery->where('venue_id', $venueId);
        }
        $untalliedQuery->delete();
        
        // Clear session roster
        $sessionQuery = Player::where('in_session', true);
        if ($venueId) {
            $sessionQuery->where('venue_id', $venueId);
        }
        $sessionQuery->update(['in_session' => false]);

        return redirect()->back()->with('success', 'Session reset completely.');
    }

    public function saveSession()
    {
        $settings = $this->venueSettings();
        $venueId = $this->activeVenueId();
        $lossPenalty = max(1, (int) ($settings['scoring_loss_penalty'] ?? 5));
        $randomizeLoss = ($settings['scoring_randomize_loss'] ?? '0') === '1' || ($settings['scoring_randomize_loss'] ?? '0') === 'true';

        $untalliedQuery = GameMatch::where('is_tallied', false);
        if ($venueId) {
            $untalliedQuery->where('venue_id', $venueId);
        }
        $untalliedMatches = $untalliedQuery->get();

        foreach ($untalliedMatches as $match) {
            $players = array_filter([
                $match->player_1_id,
                $match->player_2_id,
                $match->player_3_id,
                $match->player_4_id
            ]);

            $winners = [];
            $losers = [];

            if ($match->player_1_score > $match->player_2_score) {
                $winners = array_filter([$match->player_1_id, $match->player_3_id]);
                $losers = array_filter([$match->player_2_id, $match->player_4_id]);
            } else {
                $winners = array_filter([$match->player_2_id, $match->player_4_id]);
                $losers = array_filter([$match->player_1_id, $match->player_3_id]);
            }

            foreach ($players as $pid) {
                $p = Player::find($pid);
                if ($p) {
                    $p->increment('total_matches');
                    if (in_array($pid, $winners)) {
                        $p->increment('wins');
                    } else {
                        $p->increment('losses');
                    }
                }
            }

            $match->update([
                'is_tallied' => true,
                'loss_points' => $randomizeLoss ? rand(1, $lossPenalty) : $lossPenalty,
            ]);
        }

        // Add session players to membership roster, then clear session
        $sessionQuery = Player::where('in_session', true);
        if ($venueId) {
            $sessionQuery->where('venue_id', $venueId);
        }
        $sessionQuery->update(['show_in_roster' => true, 'in_session' => false]);

        $user = auth()->user();
        if ($user && $user->isPlayer()) {
            $activeBooking = Booking::where('status', 'approved')
                ->where('booking_date', now()->toDateString())
                ->where('user_id', $user->id)
                ->latest('start_time')
                ->first();

            if (!$activeBooking) {
                $playerProfile = Player::where('user_id', $user->id)->first();
                if ($playerProfile) {
                    $activeBooking = Booking::where('status', 'approved')
                        ->where('booking_date', now()->toDateString())
                        ->whereHas('players', fn($q) => $q->where('players.id', $playerProfile->id))
                        ->latest('start_time')
                        ->first();
                }
            }

            if ($activeBooking) {
                $activeBooking->update(['scoring_state' => null]);
                if ($activeBooking->user_id) {
                    $ownerPlayer = Player::where('user_id', $activeBooking->user_id)->first();
                    $ownerPlayerId = $ownerPlayer ? $ownerPlayer->id : null;
                    if ($ownerPlayerId) {
                        $activeBooking->players()->where('players.id', '!=', $ownerPlayerId)->detach();
                    } else {
                        $activeBooking->players()->detach();
                    }
                }
            }
        }

        return redirect()->back()->with('success', 'Session saved and board cleared.');
    }

    public function updatePlayer(Request $request, Player $player)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'full_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'birthday' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'membership_expires_at' => 'nullable|date',
            'last_monthly_due_paid_at' => 'nullable|date',
            'created_at' => 'nullable|date',
        ]);

        $createdAt = $validated['created_at'] ?? null;
        unset($validated['created_at']);

        $player->fill($validated);
        if ($createdAt) {
            $player->created_at = $createdAt;
        }
        $player->save();

        return redirect()->back();
    }

    public function destroyPlayer(Player $player)
    {
        $player->delete();

        return redirect()->back();
    }

    public function removeFromSession(Player $player)
    {
        $user = auth()->user();
        if ($user->isPlayer()) {
            $now = now();
            $booking = Booking::where('status', 'approved')
                ->where('booking_date', $now->toDateString())
                ->where('start_time', '<=', $now->toTimeString())
                ->where('end_time', '>', $now->toTimeString())
                ->where('user_id', $user->id)
                ->first();
            if ($booking) {
                $booking->players()->detach($player->id);
                $state = $booking->scoring_state;
                if ($state && is_array($state)) {
                    if (isset($state['localRegisteredPlayerIds'])) {
                        $state['localRegisteredPlayerIds'] = array_values(array_diff($state['localRegisteredPlayerIds'], [$player->id]));
                    }
                    if (isset($state['activePlayerIds'])) {
                        $state['activePlayerIds'] = array_values(array_diff($state['activePlayerIds'], [$player->id]));
                    }
                    $booking->update(['scoring_state' => $state]);
                }
            }
        } else {
            $player->update(['in_session' => false]);
        }

        return redirect()->back();
    }

    // --- Venue Setup ---

    public function venueSetup()
    {
        $user = auth()->user();
        $venue = Venue::where('scheduler_id', $user->id)->first();
        $defaultCourtCount = (int) (SystemSetting::all()->pluck('value', 'key')->get('court_count') ?? 4);

        return Inertia::render('VenueSetup', [
            'venue' => $venue,
            'default_court_count' => $defaultCourtCount,
        ]);
    }

    public function storeVenue(Request $request)
    {
        $user = auth()->user();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'facebook_url' => 'nullable|string|max:1000',
            'amenities' => 'nullable|string|max:500',
            'covered_court_count' => 'nullable|integer|min:0',
            'logo_photo' => 'nullable|image|max:10240',
            'cover_photo' => 'nullable|image|max:10240',
            'gallery_photos' => 'nullable|array',
            'gallery_photos.*' => 'image|max:10240',
            'existing_gallery_paths' => 'nullable|string',
        ]);

        $data = collect($validated)->except(['logo_photo', 'cover_photo', 'gallery_photos', 'existing_gallery_paths'])->toArray();

        if (!empty($data['amenities'])) {
            $data['amenities'] = array_map('trim', explode(',', $data['amenities']));
        }

        if ($request->hasFile('logo_photo')) {
            $data['logo_path'] = '/storage/' . $request->file('logo_photo')->store('venue-logos', 'public');
        }
        if ($request->hasFile('cover_photo')) {
            $data['cover_photo_path'] = '/storage/' . $request->file('cover_photo')->store('venue-covers', 'public');
        }

        $cleanExisting = [];
        $existingRaw = $request->input('existing_gallery_paths', '[]');
        $existingPaths = json_decode($existingRaw, true) ?? [];
        foreach ((array) $existingPaths as $path) {
            // Strip any leading /storage/ so we store only relative paths
            $cleaned = ltrim(str_replace('/storage/', '', $path), '/');
            if (!empty($cleaned)) {
                $cleanExisting[] = $cleaned;
            }
        }

        $newPaths = [];
        if ($request->hasFile('gallery_photos')) {
            foreach ($request->file('gallery_photos') as $photo) {
                $newPaths[] = $photo->store('venue-gallery', 'public');
            }
        }

        $data['gallery_paths'] = array_merge($cleanExisting, $newPaths);

        // Pull operational settings from SystemSettings so the venue is never missing them
        $settings = SystemSetting::all()->pluck('value', 'key');
        $operationalKeys = [
            'court_count', 'opening_time', 'closing_time', 'default_hourly_rate',
            'member_booking_fee', 'non_member_booking_fee', 'membership_monthly_fee', 'membership_yearly_fee',
            'walkin_member_fee', 'walkin_non_member_fee', 'walkin_ball_surcharge',
            'booking_expiration_grace_minutes', 'allow_past_edits',
            'refund_full_hours', 'refund_full_mins', 'refund_full_pct',
            'refund_partial_hours', 'refund_partial_mins', 'refund_partial_pct', 'refund_no_pct',
            'app_name',
        ];
        foreach ($operationalKeys as $key) {
            if (!isset($data[$key]) && isset($settings[$key])) {
                $data[$key] = $settings[$key];
            }
        }

        $venue = Venue::where('scheduler_id', $user->id)->first();
        $totalCourts = (int) ($data['court_count'] ?? $settings['court_count'] ?? $venue?->court_count ?? 4);
        $data['court_count'] = $totalCourts;
        if (isset($data['covered_court_count']) && $data['covered_court_count'] !== null) {
            $data['covered_court_count'] = max(0, min((int) $data['covered_court_count'], $totalCourts));
        }

        if ($venue) {
            $venue->update($data);
        } else {
            $data['scheduler_id'] = $user->id;
            $data['is_active'] = true;
            $venue = Venue::create($data);
        }

        return redirect()->route('venue-setup');
    }

    // --- Client Public Booking ---

    public function clientBooking(Request $request, ?string $venueName = null)
    {
        $this->cleanExpiredOverrides();
        $settings = $this->venueSettings();

        // Merge venue-level payment reference into settings
        $venue = $venueName ? Venue::where('name', $venueName)->first() : null;
        if (!$venue) {
            $venue = Venue::where('is_active', true)->first();
        }
        if ($venue) {
            if ($venue->payment_account_name) {
                $settings['payment_account_name'] = $venue->payment_account_name;
            }
            if ($venue->payment_qr_photo) {
                $settings['payment_qr_photo'] = $venue->payment_qr_photo;
            }
        }

        $statsData = $this->getAllTimeStatsData();

        $currentPlayerProfile = null;
        if ($request->user() && $request->user()->role === 'player') {
            $currentPlayerProfile = Player::where('user_id', $request->user()->id)->first(['id', 'phone', 'address', 'is_member']);
        }

        $bookingsQuery = Booking::whereIn('status', ['pending', 'approved', 'rejected', 'cancelled'])
            ->where('booking_date', '>=', now()->toDateString());
        if ($venue) {
            $bookingsQuery->where('venue_id', $venue->id);
        }

        $weeklyQuery = DayAvailability::orderBy('day_of_week');
        $dateQuery = DateAvailability::orderBy('date');
        if ($venue) {
            $weeklyQuery->where('venue_id', $venue->id);
            $dateQuery->where('venue_id', $venue->id);
        }

        $bookings = $bookingsQuery->with(['players' => fn($q) => $q->with('user')])->get()->map(function ($booking) {
            $owner = $booking->players->first(fn($p) => !$p->pivot->invited_by_user_id);
            if ($owner?->user) {
                $booking->lead_name = $owner->user->username ?? $owner->user->name;
            }
            return $booking;
        });

        return Inertia::render('ClientBooking', [
            'bookings' => $bookings,
            'venue' => $venue,
            'settings' => $settings,
            'weather'  => $this->fetchWeather($settings->toArray()),
            'pricing' => [
                'member_booking_rate' => (float) ($settings['member_booking_fee'] ?? 180),
                'non_member_booking_rate' => (float) ($settings['non_member_booking_fee'] ?? 200),
            ],
            'weeklyAvailabilities' => $weeklyQuery->get(),
            'dateOverrides' => $dateQuery->get(),
            'players' => $statsData['players'],
            'matches' => $statsData['matches'],
            'currentPlayerProfile' => $currentPlayerProfile,
        ]);
    }

    public function storeClientBooking(Request $request, ?string $venueName = null)
    {
        $validated = $request->validate([
            'booking_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'cost_per_hour' => 'required|numeric',
            'total_cost' => 'required|numeric',
            'lead_name' => 'required|string|max:255',
            'lead_address' => 'nullable|string|max:255',
            'guest_email' => 'nullable|email|max:255',
            'guest_phone' => 'nullable|string|max:255',
            'player_count' => 'required|integer|min:1',
            'court_number' => 'required|integer|min:1',
            'client_type' => ['required', Rule::in(['member', 'non_member'])],
            'receipt_photo' => 'nullable|image|max:5120',
        ]);

        $venue = $venueName ? Venue::where('name', $venueName)->first() : null;
        $settings = $this->venueSettings();

        $avail = $this->resolveAvailabilityForDate($validated['booking_date']);
        if ($avail['is_closed']) {
            return back()->withErrors(['booking_date' => 'This date is marked as closed.'])->withInput();
        }

        $start = new \DateTime($validated['start_time']);
        $end = new \DateTime($validated['end_time']);
        if ($end <= $start) {
            $end->modify('+1 day');
        }

        $startTimeStr = $start->format('H:i');
        $endTimeStr = $end->format('H:i');

        $timeCheck = $this->validateBookingHours($startTimeStr, $endTimeStr, $avail);
        if ($timeCheck === 'start_time_before_open') {
            return back()->withErrors(['start_time' => 'The selected start time is before the opening time for this date (' . $avail['opening_time'] . ').'])->withInput();
        }
        if ($timeCheck === 'end_time_after_close') {
            return back()->withErrors(['end_time' => 'The selected end time is after the closing time for this date (' . $avail['closing_time'] . ').'])->withInput();
        }

        $durationHours = max(0.0, ($end->getTimestamp() - $start->getTimestamp()) / 3600);
        $rate = $validated['client_type'] === 'member'
            ? (float) ($settings['member_booking_fee'] ?? 180)
            : (float) ($settings['non_member_booking_fee'] ?? 200);
        $validated['cost_per_hour'] = $rate;
        $validated['total_cost'] = round($durationHours * $rate, 2);

        $overlapping = Booking::where('court_number', $validated['court_number'])
            ->where('booking_date', $validated['booking_date'])
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_time', '<', $validated['end_time'])
            ->where('end_time', '>', $validated['start_time'])
            ->exists();

        if ($overlapping) {
            return back()
                ->withErrors(['time_slot' => 'This time slot is already booked or has a pending reservation. Please choose a different time or court.'])
                ->withInput();
        }

        $receiptPath = null;
        if ($request->hasFile('receipt_photo')) {
            $receiptPath = $request->file('receipt_photo')->store('receipts', 'public');
        }

        // Override guest details with player profile for logged-in players
        $user = $request->user();
        if ($user && $user->role === 'player') {
            $playerProfile = Player::where('user_id', $user->id)->first();
            if ($playerProfile) {
                $fullName = $user->username;
                if (empty($fullName)) {
                    $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                }
                if (empty($fullName)) {
                    $fullName = $user->name;
                }
                $validated['lead_name'] = $fullName;
                $validated['lead_address'] = $playerProfile->address ?? $validated['lead_address'];
                $validated['guest_email'] = $user->email ?? $validated['guest_email'];
                $validated['guest_phone'] = $playerProfile->phone ?? $validated['guest_phone'];
                $validated['client_type'] = $playerProfile->is_member ? 'member' : 'non_member';
            }
        }

        $booking = Booking::create([
            'booking_date' => $validated['booking_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'cost_per_hour' => $validated['cost_per_hour'],
            'total_cost' => $validated['total_cost'],
            'lead_name' => $validated['lead_name'],
            'lead_address' => $validated['lead_address'] ?? null,
            'guest_email' => $validated['guest_email'] ?? null,
            'guest_phone' => $validated['guest_phone'] ?? null,
            'player_count' => $validated['player_count'],
            'court_number' => $validated['court_number'],
            'client_type' => $validated['client_type'],
            'status' => 'pending',
            'receipt_photo' => $receiptPath,
            'user_id' => $request->user()?->id,
            'venue_id' => $venue?->id,
        ]);

        // Link the booking creator as a player in booking_player
        if ($user && $user->role === 'player' && $playerProfile) {
            $booking->players()->attach($playerProfile->id, ['status' => 'accepted']);
        }

        return redirect()->back()->with('success', 'Booking request submitted! We will review it shortly.');
    }

    public function approveBooking(Booking $booking)
    {
        $booking->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'payment_status' => 'paid',
        ]);

        // Link the booking owner as a player in booking_player if not already linked
        if ($booking->user_id) {
            $player = Player::where('user_id', $booking->user_id)->first();
            if ($player) {
                $player->update([
                    'show_in_roster' => true,
                    'venue_id' => $booking->venue_id,
                ]);
                if (!$booking->players()->where('players.id', $player->id)->exists()) {
                    $booking->players()->attach($player->id, ['status' => 'accepted']);
                }
            }
        }

        return redirect()->back()->with('success', 'Booking approved.');
    }

    public function rejectBooking(Booking $booking)
    {
        $booking->update([
            'status' => 'rejected',
            'payment_status' => 'unpaid',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return redirect()->back()->with('success', 'Booking rejected.');
    }

    public function cancelBooking(Booking $booking)
    {
        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'payment_status' => 'unpaid',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return redirect()->back()->with('success', 'Booking cancelled successfully.');
    }

    private function computeTopPlayersByBookingType(?string $type, int $limit = 10): array
    {
        $venueId = $this->activeVenueId();
        $query = GameMatch::where('is_tallied', true);
        if ($venueId) {
            $query->where('venue_id', $venueId);
        }

        if ($type === 'booking') {
            $query->whereHas('booking', fn($q) => $q->where('type', 'booking'));
        } elseif ($type === 'walk-in') {
            $query->where(function ($q) {
                $q->where('is_walkin', true)
                  ->orWhereHas('booking', fn($sq) => $sq->where('type', 'walk-in'));
            });
        } elseif ($type === 'reclub') {
            $query->whereHas('booking', fn($q) => $q->where('type', 'reclub'));
        } else {
            return [];
        }

        $matches = $query->get();
        $stats = [];

        foreach ($matches as $m) {
            $team1 = array_filter([$m->player_1_id, $m->player_3_id]);
            $team2 = array_filter([$m->player_2_id, $m->player_4_id]);
            $team1Won = $m->player_1_score > $m->player_2_score;

            foreach ($team1 as $pid) {
                if (!isset($stats[$pid])) $stats[$pid] = ['wins' => 0, 'losses' => 0];
                $team1Won ? $stats[$pid]['wins']++ : $stats[$pid]['losses']++;
            }
            foreach ($team2 as $pid) {
                if (!isset($stats[$pid])) $stats[$pid] = ['wins' => 0, 'losses' => 0];
                $team1Won ? $stats[$pid]['losses']++ : $stats[$pid]['wins']++;
            }
        }

        uasort($stats, fn($a, $b) => $b['wins'] <=> $a['wins']);

        $players = Player::whereIn('id', array_keys($stats))->get()->keyBy('id');
        $result = [];
        $count = 0;

        foreach ($stats as $pid => $s) {
            if ($count >= $limit) break;
            $player = $players->get($pid);
            if (!$player) continue;
            $total = $s['wins'] + $s['losses'];
            $result[] = [
                'id' => $player->id,
                'name' => $player->name,
                'wins' => $s['wins'],
                'losses' => $s['losses'],
                'total_matches' => $total,
                'win_rate' => $total > 0 ? round(($s['wins'] / $total) * 100, 1) : 0,
            ];
            $count++;
        }

        return $result;
    }

    public function storeScoringState(Request $request)
    {
        $user = auth()->user();
        $bookingId = $request->input('booking_id');
        $booking = null;

        if ($bookingId) {
            $booking = Booking::find($bookingId);
        } else if ($user && $user->isPlayer()) {
            $player = Player::where('user_id', $user->id)->first();
            if ($player) {
                $now = now();
                $booking = Booking::where('status', 'approved')
                    ->where('booking_date', $now->toDateString())
                    ->where('start_time', '<=', $now->toTimeString())
                    ->where('end_time', '>', $now->toTimeString())
                    ->where(function ($q) use ($user, $player) {
                        $q->whereHas('players', fn($sq) => $sq->where('players.id', $player->id))
                          ->orWhere('user_id', $user->id);
                    })
                    ->first();
            }
        }

        if (!$booking) {
            return response()->json(['error' => 'No active booking found'], 404);
        }

        $booking->update([
            'scoring_state' => $request->input('state'),
        ]);

        return response()->json(['success' => true]);
    }
}
