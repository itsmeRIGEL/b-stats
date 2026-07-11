<?php

namespace App\Http\Middleware;

use App\Models\Booking;
use App\Models\Player;
use App\Models\SystemSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function handle(Request $request, \Closure $next): Response
    {
        $response = parent::handle($request, $next);

        if ($response instanceof \Illuminate\Contracts\Support\Responsable) {
            $response = $response->toResponse($request);
        }

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Cache system settings for better performance
        $systemSettings = cache()->remember('system_settings', 300, function () {
            return SystemSetting::all()->pluck('value', 'key');
        });

        // Optimize user data sharing - only essential fields for auth check
        $user = $request->user();
        if ($user && !$request->session()->has('active_role')) {
            $userRole = $user->role ?? 'admin';
            $defaultRole = $userRole === 'scheduler_scorer' ? 'scheduler' : $userRole;
            $request->session()->put('active_role', $defaultRole);
        }

        $bookingInvitations = [];
        $notifications = [];
        $now = now();

        if ($user && ($user->role ?? null) === 'player' && Schema::hasTable('booking_player') && Schema::hasColumn('booking_player', 'status')) {
            $bookingInvitations = Booking::query()
                ->with([
                    'venue:id,name',
                    'user:id,name,username',
                    'players' => fn ($query) => $query->with('user:id,name,username')->withPivot(['status', 'invited_by_user_id', 'responded_at']),
                ])
                ->whereHas('players', function ($query) use ($user) {
                    $query->where('players.user_id', $user->id)
                        ->where('booking_player.status', 'pending');
                })
                ->where('user_id', '!=', $user->id)
                ->whereDate('booking_date', '>=', now()->copy()->subDays(2)->toDateString())
                ->orderBy('booking_date')
                ->orderBy('start_time')
                ->get()
                ->map(function (Booking $booking) use ($user) {
                    $invitedPlayer = $booking->players->first(fn ($player) => (int) $player->user_id === (int) $user->id && ($player->pivot->status ?? null) === 'pending' && (int) ($player->pivot->invited_by_user_id ?? 0) !== (int) $user->id);

                    if (!$invitedPlayer) {
                        return null;
                    }

                    return [
                        'booking_id' => $booking->id,
                        'venue_name' => $booking->venue?->name,
                        'lead_name' => $booking->lead_name,
                        'court_number' => $booking->court_number,
                        'booking_date' => $booking->booking_date,
                        'start_time' => $booking->start_time,
                        'end_time' => $booking->end_time,
                        'invited_by' => $booking->user?->username ?? $booking->user?->name ?? $booking->lead_name,
                        'player_name' => $invitedPlayer->user?->username ?? $invitedPlayer->user?->name ?? $invitedPlayer->full_name ?? $invitedPlayer->name,
                        'status' => $invitedPlayer->pivot->status ?? 'pending',
                    ];
                })
                ->filter()
                ->values()
                ->all();

            // Populate invitations as notification items
            foreach ($bookingInvitations as $invite) {
                $notifications[] = [
                    'id' => 'invite-' . $invite['booking_id'],
                    'type' => 'invitation',
                    'title' => 'Booking Invitation',
                    'message' => $invite['invited_by'] . " invited you to play at " . ($invite['venue_name'] ?? 'Venue') . " (Court " . $invite['court_number'] . ") on " . $invite['booking_date'] . " (" . substr($invite['start_time'], 0, 5) . " - " . substr($invite['end_time'], 0, 5) . ")",
                    'created_at' => $now->toIso8601String(),
                    'action_url' => '/scoring',
                    'meta' => $invite,
                ];
            }

            // Booking status updates (for players)
            $playerBookings = Booking::where('user_id', $user->id)
                ->whereIn('status', ['approved', 'rejected'])
                ->where('updated_at', '>=', now()->subDays(5))
                ->get();
            foreach ($playerBookings as $b) {
                $notifications[] = [
                    'id' => 'booking-status-' . $b->id . '-' . $b->status,
                    'type' => 'booking',
                    'title' => 'Booking ' . ucfirst($b->status),
                    'message' => "Your booking request for " . $b->booking_date . " on Court " . $b->court_number . " has been " . $b->status . ".",
                    'created_at' => $b->updated_at?->toIso8601String() ?? $now->toIso8601String(),
                    'action_url' => '/all-time-stats',
                    'meta' => [
                        'booking_id' => $b->id,
                        'status' => $b->status,
                    ]
                ];
            }

            // Tournament Requests status updates (for players)
            if (Schema::hasTable('tournament_requests')) {
                $myTournamentRequests = \App\Models\TournamentRequest::where('user_id', $user->id)
                    ->whereIn('status', ['approved', 'rejected'])
                    ->where('updated_at', '>=', now()->subDays(5))
                    ->get();
                foreach ($myTournamentRequests as $tr) {
                    $notifications[] = [
                        'id' => 'tr-status-' . $tr->id . '-' . $tr->status,
                        'type' => 'tournament',
                        'title' => 'Tournament Request ' . ucfirst($tr->status),
                        'message' => "Your tournament request '" . $tr->name . "' has been " . $tr->status . ($tr->rejection_reason ? " Reason: " . $tr->rejection_reason : ""),
                        'created_at' => $tr->updated_at?->toIso8601String() ?? $now->toIso8601String(),
                        'action_url' => '/tournaments',
                        'meta' => [
                            'request_id' => $tr->id,
                            'status' => $tr->status,
                        ]
                    ];
                }
            }

            // Membership access alerts (for players)
            $playerProfile = Player::where('user_id', $user->id)->first();
            if ($playerProfile && $playerProfile->is_member) {
                if ($playerProfile->membership_expires_at && $playerProfile->membership_expires_at->diffInDays(now()) <= 30) {
                    $notifications[] = [
                        'id' => 'membership-expiry-' . $playerProfile->id,
                        'type' => 'membership',
                        'title' => 'Membership Expiring Soon',
                        'message' => "Your annual membership will expire on " . $playerProfile->membership_expires_at->toDateString() . ". Please renew soon.",
                        'created_at' => $now->toIso8601String(),
                        'action_url' => '/all-time-stats',
                    ];
                }
            }
        }

        // Bookings and Tournament requests (for Scheduler/Admin)
        if ($user && in_array($user->role, ['admin', 'scheduler', 'scheduler_scorer'], true)) {
            $pendingBookings = Booking::where('status', 'pending')
                ->whereDate('booking_date', '>=', now()->toDateString())
                ->get();
            foreach ($pendingBookings as $pb) {
                $notifications[] = [
                    'id' => 'pending-booking-' . $pb->id,
                    'type' => 'booking',
                    'title' => 'Pending Booking Request',
                    'message' => "New court booking request from " . $pb->lead_name . " on " . $pb->booking_date,
                    'created_at' => $pb->created_at?->toIso8601String() ?? $now->toIso8601String(),
                    'action_url' => '/bookings',
                    'meta' => [
                        'booking_id' => $pb->id,
                    ]
                ];
            }

            if (Schema::hasTable('tournament_requests')) {
                $pendingTRs = \App\Models\TournamentRequest::where('status', 'pending')->with('user')->get();
                foreach ($pendingTRs as $ptr) {
                    $notifications[] = [
                        'id' => 'pending-tr-' . $ptr->id,
                        'type' => 'tournament',
                        'title' => 'Pending Tournament Request',
                        'message' => "New tournament request '" . $ptr->name . "' from " . ($ptr->user?->name ?? 'Guest'),
                        'created_at' => $ptr->created_at?->toIso8601String() ?? $now->toIso8601String(),
                        'action_url' => '/tournament-requests',
                        'meta' => [
                            'request_id' => $ptr->id,
                        ]
                    ];
                }
            }
        }

        $authUser = $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'suffix' => $user->suffix,
            'gender' => $user->gender,
            'gender_other' => $user->gender_other,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'avatar' => $user->avatar,
            'facebook_url' => $user->facebook_url,
            'instagram_url' => $user->instagram_url,
            'website_url' => $user->website_url,
            'role' => $request->session()->get('active_role', $user->role ?? 'admin'),
            'db_role' => $user->role ?? 'admin',
        ] : null;
        $currentVenue = $user && method_exists($user, 'currentVenue') ? $user->currentVenue() : null;

        return array_merge(parent::share($request), [
            'name' => $systemSettings['app_name'] ?? config('app.name'),
            'appLogo' => $systemSettings['app_logo'] ?? null,
            'currentVenue' => $currentVenue ? [
                'id' => $currentVenue->id,
                'name' => $currentVenue->name,
            ] : null,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'new_sub_folder_id' => $request->session()->get('new_sub_folder_id'),
            ],
            'auth' => [
                'user' => $authUser,
            ],
            'bookingInvitations' => $bookingInvitations,
            'notifications' => $notifications,
        ]);
    }
}





