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

class PickleballController extends Controller
{
    private const MEMBER_BOOKING_RATE = 180;
    private const NON_MEMBER_BOOKING_RATE = 200;
    private const WALKIN_MEMBER_FEE = 15;
    private const WALKIN_NON_MEMBER_FEE = 20;
    private const WALKIN_BALL_SURCHARGE = 5;

    public function dashboard()
    {
        $settings = SystemSetting::all()->pluck('value', 'key');
        $today = now()->toDateString();

        $todayBookingRev = Booking::where('type', 'booking')->where('payment_status', 'paid')->whereDate('booking_date', $today)->sum('total_cost');
        $todayReclubRev = Booking::where('type', 'reclub')->where('payment_status', 'paid')->whereDate('booking_date', $today)->sum('total_cost');
        $todayWalkinRev = GameMatch::where('is_walkin', true)->whereDate('match_date', $today)->sum('fee_amount');
        $todayMembershipRev = MembershipPayment::whereNull('revoked_at')->whereDate('paid_at', $today)->sum('amount');
        $todayTotalRev = $todayBookingRev + $todayReclubRev + $todayWalkinRev + $todayMembershipRev;

        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();
        $weeklyBookingRev = Booking::where('type', 'booking')->where('payment_status', 'paid')->whereBetween('booking_date', [$weekStart, $weekEnd])->sum('total_cost');
        $weeklyReclubRev = Booking::where('type', 'reclub')->where('payment_status', 'paid')->whereBetween('booking_date', [$weekStart, $weekEnd])->sum('total_cost');
        $weeklyWalkinRev = GameMatch::where('is_walkin', true)->whereBetween('match_date', [$weekStart, $weekEnd])->sum('fee_amount');
        $weeklyMembershipRev = MembershipPayment::whereNull('revoked_at')->whereBetween('paid_at', [$weekStart, $weekEnd])->sum('amount');

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $monthlyBookingRev = Booking::where('type', 'booking')->where('payment_status', 'paid')->whereBetween('booking_date', [$monthStart, $monthEnd])->sum('total_cost');
        $monthlyReclubRev = Booking::where('type', 'reclub')->where('payment_status', 'paid')->whereBetween('booking_date', [$monthStart, $monthEnd])->sum('total_cost');
        $monthlyWalkinRev = GameMatch::where('is_walkin', true)->whereBetween('match_date', [$monthStart, $monthEnd])->sum('fee_amount');
        $monthlyMembershipRev = MembershipPayment::whereNull('revoked_at')->whereBetween('paid_at', [$monthStart, $monthEnd])->sum('amount');

        return Inertia::render('Dashboard', [
            'total_players' => Player::count(),
            'active_members' => Player::where('is_member', true)->count(),
            'upcoming_bookings' => Booking::with('players')->where('booking_date', '>=', now()->toDateString())->get(),
            'top_players' => Player::orderBy('wins', 'desc')->take(5)->get(),
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
        return Inertia::render('PickleballSettings', [
            'settings' => SystemSetting::all()->pluck('value', 'key'),
            'weeklyAvailabilities' => DayAvailability::orderBy('day_of_week')->get(),
            'dateOverrides' => DateAvailability::orderBy('date')->get(),
        ]);
    }

    public function resolveAvailabilityForDate($date)
    {
        $this->cleanExpiredOverrides();
        // 1. Check for specific date override
        $dateOverride = DateAvailability::where('date', $date)->first();
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
        $daySetting = DayAvailability::where('day_of_week', $dayOfWeek)->first();
        if ($daySetting && $daySetting->is_enabled) {
            return [
                'is_closed' => (bool)$daySetting->is_closed,
                'opening_time' => $daySetting->opening_time ? substr($daySetting->opening_time, 0, 5) : null,
                'closing_time' => $daySetting->closing_time ? substr($daySetting->closing_time, 0, 5) : null,
                'close_reason' => $daySetting->close_reason,
            ];
        }

        // 3. Fallback to default system settings
        $settings = SystemSetting::all()->pluck('value', 'key');
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

        foreach ($validated['schedules'] as $sched) {
            DayAvailability::updateOrCreate(
                ['day_of_week' => $sched['day_of_week']],
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

        DateAvailability::updateOrCreate(
            ['date' => $validated['date']],
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

        $allowedRoles = $isAdmin
            ? ['admin', 'scheduler', 'scorer', 'scheduler_scorer']
            : ['scheduler', 'scorer', 'scheduler_scorer'];

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
            : ['scheduler', 'scorer', 'scheduler_scorer'];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in($allowedRoles)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'allow_unverified_access' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
            'allow_unverified_access' => (bool) ($validated['allow_unverified_access'] ?? false),
        ]);

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
                'default_hourly_rate', 'member_booking_fee', 'non_member_booking_fee', 'membership_monthly_fee', 'membership_yearly_fee', 'walkin_member_fee', 'walkin_non_member_fee', 'walkin_ball_surcharge'
            ];
            $data = array_intersect_key($data, array_flip($allowedKeys));
        }

        if ($request->hasFile('app_logo')) {
            $path = $request->file('app_logo')->store('logos', 'public');
            $data['app_logo'] = '/storage/' . $path;
        } else {
            unset($data['app_logo']);
        }

        if ($request->hasFile('payment_qr_photo') && (!$currentUser || $currentUser->role === 'admin')) {
            $path = $request->file('payment_qr_photo')->store('payment-qrs', 'public');
            $data['payment_qr_photo'] = '/storage/' . $path;
        } else {
            unset($data['payment_qr_photo']);
        }

        foreach ($data as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return redirect()->back()->with('success', 'Settings updated successfully.');
    }

    public function bookings()
    {
        $settings = SystemSetting::all()->pluck('value', 'key');
        $today = now()->toDateString();

        $courtAssignments = CourtScorerAssignment::where('assignment_date', $today)
            ->with('scorer:id,name')
            ->get()
            ->keyBy('court_number')
            ->map(fn($a) => ['scorer_id' => $a->scorer_id, 'scorer_name' => $a->scorer?->name]);

        $courtCount = (int) ($settings['court_count'] ?? 4);
        $allCourtNumbers = range(1, $courtCount);
        $missingCourts = array_diff($allCourtNumbers, $courtAssignments->keys()->toArray());

        if (!empty($missingCourts)) {
            $fallbacks = CourtScorerAssignment::whereIn('court_number', $missingCourts)
                ->where('scorer_id', '!=', null)
                ->orderByDesc('assignment_date')
                ->with('scorer:id,name')
                ->get()
                ->unique('court_number');

            foreach ($fallbacks as $fb) {
                $courtAssignments[$fb->court_number] = [
                    'scorer_id' => $fb->scorer_id,
                    'scorer_name' => $fb->scorer?->name,
                ];
            }
        }

        $this->cleanExpiredOverrides();
        return Inertia::render('Bookings', [
            'bookings' => Booking::with(['players', 'scorer', 'approver:id,name'])->orderBy('booking_date', 'desc')->get(),
            'players' => Player::all(),
            'scorers' => \App\Models\User::whereIn('role', ['scorer', 'scheduler_scorer'])->select('id', 'name')->get(),
            'courtAssignments' => $courtAssignments,
            'settings' => $settings,
            'weather'  => $this->fetchWeather($settings->toArray()),
            'weeklyAvailabilities' => DayAvailability::orderBy('day_of_week')->get(),
            'dateOverrides' => DateAvailability::orderBy('date')->get(),
        ]);
    }

    public function saveCourtAssignment(Request $request)
    {
        $validated = $request->validate([
            'court_number' => 'required|integer|min:1',
            'scorer_id' => 'nullable|exists:users,id',
            'assignment_date' => 'required|date',
        ]);

        CourtScorerAssignment::updateOrCreate(
            [
                'court_number' => $validated['court_number'],
                'assignment_date' => $validated['assignment_date'],
            ],
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

        $settings = SystemSetting::all()->pluck('value', 'key');
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

        $settings = SystemSetting::all()->pluck('value', 'key');
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
            'scorer_id' => 'nullable|exists:users,id',
            'type' => 'required|string|in:booking,walk-in,reclub',
        ]);
    }

    public function destroyBooking(Booking $booking)
    {
        $booking->players()->detach();
        $booking->delete();

        return redirect()->back()->with('success', 'Booking deleted successfully.');
    }

    public function memberships()
    {
        return Inertia::render('Memberships', [
            'players' => Player::where('show_in_roster', true)->get(),
            'settings' => SystemSetting::all()->pluck('value', 'key'),
        ]);
    }

    public function toggleMembership(Player $player)
    {
        $settings = SystemSetting::all()->pluck('value', 'key');
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
        if (!$player->is_member) {
            return redirect()->back()->with('error', 'Player is not an active member.');
        }

        $settings = SystemSetting::all()->pluck('value', 'key');
        $monthlyFee = (float) ($settings['membership_monthly_fee'] ?? 15);

        if ($monthlyFee > 0) {
            \App\Models\MembershipPayment::create([
                'player_id' => $player->id,
                'amount' => $monthlyFee,
                'billing_period' => 'monthly',
                'paid_at' => now(),
            ]);

            $player->update(['last_monthly_due_paid_at' => now()]);
        }

        return redirect()->back();
    }

    public function revokeMonthlyDue(Player $player)
    {
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
        $settings = SystemSetting::all()->pluck('value', 'key');

        // Get assigned courts for scorer users via court_scorer_assignments (with fallback to most recent assignments)
        $assignedCourts = [];
        if ($user && $user->isScorer()) {
            $today = now()->toDateString();
            
            // Get today's explicit assignments
            $assignmentsToday = CourtScorerAssignment::where('assignment_date', $today)
                ->get()
                ->keyBy('court_number');
                
            $courtCount = (int) ($settings['court_count'] ?? 4);
            $allCourtNumbers = range(1, $courtCount);
            
            // For any court lacking an assignment today, fall back to its most recent assignment
            $missingCourts = array_diff($allCourtNumbers, $assignmentsToday->keys()->toArray());
            if (!empty($missingCourts)) {
                $fallbacks = CourtScorerAssignment::whereIn('court_number', $missingCourts)
                    ->whereNotNull('scorer_id')
                    ->orderByDesc('assignment_date')
                    ->get()
                    ->unique('court_number')
                    ->keyBy('court_number');
                
                foreach ($fallbacks as $courtNumber => $fb) {
                    $assignmentsToday[$courtNumber] = $fb;
                }
            }
            
            // Filter courts assigned to this logged-in scorer
            $assignedCourts = $assignmentsToday
                ->filter(fn($a) => $a->scorer_id == $user->id)
                ->keys()
                ->values()
                ->toArray();
        }

        $players = Player::where('in_session', true)->get()->map(function ($player) {
            $matchesAsP1 = GameMatch::where('is_tallied', false)->where('player_1_id', $player->id)->get();
            $matchesAsP2 = GameMatch::where('is_tallied', false)->where('player_2_id', $player->id)->get();
            $matchesAsP3 = GameMatch::where('is_tallied', false)->where('player_3_id', $player->id)->get();
            $matchesAsP4 = GameMatch::where('is_tallied', false)->where('player_4_id', $player->id)->get();

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
        $activeBookings = Booking::where('booking_date', $now->toDateString())
            ->where('start_time', '<=', $now->toTimeString())
            ->where(function ($query) use ($graceTime) {
                $query->where('end_time', '>=', $graceTime)
                      ->orWhereRaw('end_time < start_time');
            })
            ->where('status', 'approved')
            ->get(['id', 'court_number', 'type', 'start_time', 'end_time', 'lead_name'])
            ->keyBy('court_number');

        $winPoints = max(1, (int) ($settings['scoring_win_points'] ?? 10));
        $lossPenalty = max(1, (int) ($settings['scoring_loss_penalty'] ?? 5));

        return Inertia::render('Scoring', [
            'matches' => GameMatch::with(['player1', 'player2', 'player3', 'player4', 'booking'])
                            ->where('is_tallied', false)
                            ->orderBy('created_at', 'desc')
                            ->get(),
            'players' => $players,
            'allPlayers' => Player::select('id', 'name', 'is_member')->get(),
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
            ],
            'assignedCourts' => $assignedCourts,
            'activeBookings' => $activeBookings,
        ]);
    }

    private function getAllTimeStatsData()
    {
        $settings = SystemSetting::all()->pluck('value', 'key');
        $winPoints = max(1, (int) ($settings['scoring_win_points'] ?? 10));

        $allPlayers = Player::all()->filter(fn($p) => ($p->wins + $p->losses) > 0);

        $buildStats = function ($players, $matchesQuery) use ($winPoints) {
            $matchIds = $matchesQuery->pluck('id');
            return $players->map(function ($player) use ($winPoints, $matchIds) {
                $total = $player->wins + $player->losses;
                $winRate = $total > 0 ? round(($player->wins / $total) * 100, 1) : 0;

                $lossPoints = GameMatch::whereIn('id', $matchIds)
                    ->where(function ($q) use ($player) {
                        $q->where(function ($sub) use ($player) {
                            $sub->whereRaw('player_1_score < player_2_score')
                                ->where(function ($inner) use ($player) {
                                    $inner->where('player_1_id', $player->id)
                                          ->orWhere('player_3_id', $player->id);
                                });
                        })->orWhere(function ($sub) use ($player) {
                            $sub->whereRaw('player_2_score < player_1_score')
                                ->where(function ($inner) use ($player) {
                                    $inner->where('player_2_id', $player->id)
                                          ->orWhere('player_4_id', $player->id);
                                });
                        });
                    })
                    ->sum('loss_points');

                $points = ($player->wins * $winPoints) - (int) $lossPoints;

                return [
                    'id' => $player->id,
                    'name' => $player->name,
                    'total_matches' => $total,
                    'wins' => $player->wins,
                    'losses' => $player->losses,
                    'win_rate' => $winRate,
                    'points' => $points,
                ];
            })->values();
        };

        $baseMatchQuery = GameMatch::where('is_tallied', true);
        $matches = (clone $baseMatchQuery)->get();

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
                        'players' => array_filter([$match->player1?->name, $match->player3?->name]),
                        'player_ids' => array_values(array_filter([$match->player_1_id, $match->player_3_id])),
                        'score' => $team1Score,
                        'won' => $team1Won,
                    ],
                    'team2' => [
                        'players' => array_filter([$match->player2?->name, $match->player4?->name]),
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
    }

    public function allTimeStats()
    {
        $settings = SystemSetting::all()->pluck('value', 'key');
        $winPoints = max(1, (int) ($settings['scoring_win_points'] ?? 10));
        $statsData = $this->getAllTimeStatsData();

        return Inertia::render('AllTimeStats', [
            'players' => $statsData['players'],
            'matches' => $statsData['matches'],
            'settings' => [
                'scoring_win_points' => $winPoints,
                'scoring_loss_penalty' => max(1, (int) ($settings['scoring_loss_penalty'] ?? 5)),
            ],
        ]);
    }

    public function storeMatch(Request $request)
    {
        $validated = $request->validate([
            'player_1_id' => 'required|exists:players,id',
            'player_2_id' => 'required|exists:players,id',
            'player_3_id' => 'nullable|exists:players,id',
            'player_4_id' => 'nullable|exists:players,id',
            'player_1_score' => 'required|integer',
            'player_2_score' => 'required|integer|different:player_1_score',
            'match_date' => 'required|date',
            'is_walkin' => 'nullable|boolean',
            'walkin_fee_type' => ['nullable', Rule::in(['with_ball', 'without_ball'])],
            'booking_id' => 'nullable|integer|exists:bookings,id',
        ]);

        $settings = SystemSetting::all()->pluck('value', 'key');
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
        ]));

        return redirect()->back();
    }

    public function updateMatch(Request $request, GameMatch $match)
    {
        $validated = $request->validate([
            'player_1_id' => 'required|exists:players,id',
            'player_2_id' => 'required|exists:players,id',
            'player_3_id' => 'nullable|exists:players,id',
            'player_4_id' => 'nullable|exists:players,id',
            'player_1_score' => 'required|integer',
            'player_2_score' => 'required|integer|different:player_1_score',
            'match_date' => 'required|date',
            'is_walkin' => 'nullable|boolean',
            'walkin_fee_type' => ['nullable', Rule::in(['with_ball', 'without_ball'])],
            'booking_id' => 'nullable|integer|exists:bookings,id',
        ]);

        $settings = SystemSetting::all()->pluck('value', 'key');
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

        foreach ($validated['names'] as $name) {
            $player = Player::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
            if ($player) {
                $player->update(['in_session' => true]);
            } else {
                Player::create([
                    'name' => $name,
                    'in_session' => true,
                    'show_in_roster' => false,
                ]);
            }
        }

        return redirect()->back();
    }

    public function resetSession()
    {
        // Delete untallied matches recorded today
        GameMatch::where('is_tallied', false)->delete();
        
        // Clear session roster
        Player::where('in_session', true)->update(['in_session' => false]);

        return redirect()->back()->with('success', 'Session reset completely.');
    }

    public function saveSession()
    {
        $settings = SystemSetting::all()->pluck('value', 'key');
        $lossPenalty = max(1, (int) ($settings['scoring_loss_penalty'] ?? 5));
        $randomizeLoss = ($settings['scoring_randomize_loss'] ?? '0') === '1' || ($settings['scoring_randomize_loss'] ?? '0') === 'true';

        $untalliedMatches = GameMatch::where('is_tallied', false)->get();

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
        Player::where('in_session', true)->update(['show_in_roster' => true, 'in_session' => false]);

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
        $player->update(['in_session' => false]);

        return redirect()->back();
    }

    // --- Client Public Booking ---

    public function clientBooking()
    {
        $this->cleanExpiredOverrides();
        $settings = SystemSetting::all()->pluck('value', 'key');
        $statsData = $this->getAllTimeStatsData();
        return Inertia::render('ClientBooking', [
            'bookings' => Booking::whereIn('status', ['pending', 'approved', 'cancelled'])
                ->where('booking_date', '>=', now()->toDateString())
                ->get(),
            'settings' => $settings,
            'weather'  => $this->fetchWeather($settings->toArray()),
            'pricing' => [
                'member_booking_rate' => (float) ($settings['member_booking_fee'] ?? 180),
                'non_member_booking_rate' => (float) ($settings['non_member_booking_fee'] ?? 200),
            ],
            'weeklyAvailabilities' => DayAvailability::orderBy('day_of_week')->get(),
            'dateOverrides' => DateAvailability::orderBy('date')->get(),
            'players' => $statsData['players'],
            'matches' => $statsData['matches'],
        ]);
    }

    public function storeClientBooking(Request $request)
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

        $settings = SystemSetting::all()->pluck('value', 'key');

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

        Booking::create([
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
        ]);

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
        $query = GameMatch::where('is_tallied', true);

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
}
