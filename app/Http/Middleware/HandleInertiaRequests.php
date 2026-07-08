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
        ]);
    }
}





