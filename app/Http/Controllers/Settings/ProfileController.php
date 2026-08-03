<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    private const ALL_TIME_STATS_VISIBILITY_FIELDS = [
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'gender',
        'username',
        'birthday',
        'address',
        'facebook_url',
        'instagram_url',
        'website_url',
    ];

    private function allTimeStatsVisibleFields(?User $user): array
    {
        if ($user?->all_time_stats_visible_fields === null) {
            return self::ALL_TIME_STATS_VISIBILITY_FIELDS;
        }

        return collect($user->all_time_stats_visible_fields)
            ->filter(fn ($field) => in_array($field, self::ALL_TIME_STATS_VISIBILITY_FIELDS, true))
            ->values()
            ->all();
    }

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $venueId = $user?->currentVenue()?->id;
        $playerProfile = Player::where('user_id', $user?->id)
            ->when($venueId, fn ($query) => $query->where('venue_id', $venueId))
            ->latest('id')
            ->first()
            ?? Player::where('user_id', $user?->id)->latest('id')->first();
        $playerProfiles = Player::where('user_id', $user?->id)->get();
        $playerIds = $playerProfiles->pluck('id')->filter()->all();
        $playerVenueIds = $playerProfiles->pluck('venue_id')->filter()->all();

        $matchVenueIds = $playerIds !== []
            ? GameMatch::where('is_tallied', true)
                ->where(function ($q) use ($playerIds) {
                    $q->whereIn('player_1_id', $playerIds)
                      ->orWhereIn('player_2_id', $playerIds)
                      ->orWhereIn('player_3_id', $playerIds)
                      ->orWhereIn('player_4_id', $playerIds);
                })
                ->whereNotNull('venue_id')
                ->pluck('venue_id')
                ->all()
            : [];

        $bookingVenueIds = Booking::where('user_id', $user?->id)
            ->whereNotNull('venue_id')
            ->pluck('venue_id')
            ->all();

        $allVenueIds = array_values(array_unique(array_filter(array_merge($playerVenueIds, $matchVenueIds, $bookingVenueIds))));

        $matchesCountByVenue = [];
        if ($playerIds !== []) {
            $counts = GameMatch::where('is_tallied', true)
                ->where(function ($q) use ($playerIds) {
                    $q->whereIn('player_1_id', $playerIds)
                      ->orWhereIn('player_2_id', $playerIds)
                      ->orWhereIn('player_3_id', $playerIds)
                      ->orWhereIn('player_4_id', $playerIds);
                })
                ->whereNotNull('venue_id')
                ->selectRaw('venue_id, count(*) as total')
                ->groupBy('venue_id')
                ->pluck('total', 'venue_id')
                ->all();
            $matchesCountByVenue = $counts;
        }

        foreach ($playerProfiles as $pp) {
            if ($pp->venue_id) {
                $matchesCountByVenue[$pp->venue_id] = max((int) ($matchesCountByVenue[$pp->venue_id] ?? 0), (int) ($pp->total_matches ?? 0));
            }
        }

        $winPoints = max(1, (int) (SystemSetting::where('key', 'scoring_win_points')->value('value') ?? 10));
        $lossPenalty = max(1, (int) (SystemSetting::where('key', 'scoring_loss_penalty')->value('value') ?? 5));

        $playedVenues = $allVenueIds !== []
            ? Venue::query()
                ->whereIn('id', $allVenueIds)
                ->get(['id', 'name'])
                ->map(function (Venue $venue) use ($playerProfile, $playerProfiles, $matchesCountByVenue, $playerIds, $winPoints, $lossPenalty) {
                    $ppForVenue = $playerProfiles->firstWhere('venue_id', $venue->id);
                    $isMember = (bool) ($ppForVenue?->is_member ?? false);
                    $expiresAt = $ppForVenue?->membership_expires_at
                        ? Carbon::parse($ppForVenue->membership_expires_at)->format('M d, Y')
                        : null;

                    $vWins = (int) ($ppForVenue?->wins ?? 0);
                    $vLosses = (int) ($ppForVenue?->losses ?? 0);
                    $vMatches = max((int) ($ppForVenue?->total_matches ?? 0), (int) ($matchesCountByVenue[$venue->id] ?? 0));

                    if ($playerIds !== [] && ($vWins === 0 && $vLosses === 0)) {
                        $talliedMatches = GameMatch::where('venue_id', $venue->id)
                            ->where('is_tallied', true)
                            ->where(function ($q) use ($playerIds) {
                                $q->whereIn('player_1_id', $playerIds)
                                  ->orWhereIn('player_2_id', $playerIds)
                                  ->orWhereIn('player_3_id', $playerIds)
                                  ->orWhereIn('player_4_id', $playerIds);
                            })
                            ->get();

                        foreach ($talliedMatches as $m) {
                            $isTeam1 = in_array($m->player_1_id, $playerIds, true) || in_array($m->player_3_id, $playerIds, true);
                            $isTeam2 = in_array($m->player_2_id, $playerIds, true) || in_array($m->player_4_id, $playerIds, true);
                            if (($isTeam1 && (int) $m->winning_team === 1) || ($isTeam2 && (int) $m->winning_team === 2)) {
                                $vWins++;
                            } elseif ($isTeam1 || $isTeam2) {
                                $vLosses++;
                            }
                        }
                        $vMatches = max($vMatches, count($talliedMatches), $vWins + $vLosses);
                    }

                    $vPoints = ($vWins * $winPoints) - ($vLosses * $lossPenalty);
                    $vWinRate = $vMatches > 0 ? round(($vWins / $vMatches) * 100, 1) : 0;

                    return [
                        'id' => $venue->id,
                        'name' => $venue->name,
                        'wins' => $vWins,
                        'losses' => $vLosses,
                        'matches_count' => $vMatches,
                        'points' => $vPoints,
                        'win_rate' => $vWinRate,
                        'is_primary' => $venue->id === $playerProfile?->venue_id,
                        'is_member' => $isMember,
                        'membership_expires_at' => $expiresAt,
                    ];
                })
                ->sortByDesc('is_primary')
                ->values()
                ->all()
            : [];
        $statsVenueName = $playerProfile?->venue_id
            ? Venue::query()->whereKey($playerProfile->venue_id)->value('name')
            : null;

        $winPoints = max(1, (int) (SystemSetting::where('key', 'scoring_win_points')->value('value') ?? 10));
        $lossPenalty = max(1, (int) (SystemSetting::where('key', 'scoring_loss_penalty')->value('value') ?? 5));
        $wins = (int) $playerProfiles->sum('wins');
        $losses = (int) $playerProfiles->sum('losses');
        $sumPlayedVenuesMatches = (int) collect($playedVenues)->sum('matches_count');
        $totalMatches = max((int) $playerProfiles->sum('total_matches'), $sumPlayedVenuesMatches);

        if ($playerIds !== []) {
            $actualTotalMatches = GameMatch::where('is_tallied', true)
                ->where(function ($q) use ($playerIds) {
                    $q->whereIn('player_1_id', $playerIds)
                      ->orWhereIn('player_2_id', $playerIds)
                      ->orWhereIn('player_3_id', $playerIds)
                      ->orWhereIn('player_4_id', $playerIds);
                })
                ->count();
            $totalMatches = max($totalMatches, $actualTotalMatches);
        }

        $points = ($wins * $winPoints) - ($losses * $lossPenalty);
        $winRate = $totalMatches > 0 ? round(($wins / $totalMatches) * 100, 1) : 0;

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'playerProfile' => $playerProfile ? [
                'id' => $playerProfile->id,
                'phone' => $playerProfile->phone,
                'birthday' => $playerProfile->birthday ? Carbon::parse($playerProfile->birthday)->toDateString() : null,
                'address' => $playerProfile->address,
                'is_member' => (bool) $playerProfile->is_member,
                'membership_expires_at' => optional($playerProfile->membership_expires_at)->toIso8601String(),
                'facebook_url' => $user?->facebook_url,
                'instagram_url' => $user?->instagram_url,
                'website_url' => $user?->website_url,
                'wins' => $wins,
                'losses' => $losses,
                'total_matches' => $totalMatches,
                'points' => $points,
                'win_rate' => $winRate,
                'venue_id' => $playerProfile->venue_id,
                'venue_name' => $statsVenueName,
            ] : null,
            'profileStats' => [
                'wins' => $wins,
                'losses' => $losses,
                'total_matches' => $totalMatches,
                'points' => $points,
                'win_rate' => $winRate,
            ],
            'playedVenues' => $playedVenues,
            'allTimeStatsVisibleFields' => $this->allTimeStatsVisibleFields($user),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        if (($data['gender'] ?? null) !== 'other') {
            $data['gender_other'] = null;
        }
        $fullName = trim(implode(' ', array_filter([
            $data['first_name'] ?? null,
            $data['middle_name'] ?? null,
            $data['last_name'] ?? null,
            $data['suffix'] ?? null,
        ])));
        $venueId = $user?->currentVenue()?->id;
        $playerProfile = Player::where('user_id', $user->id)
            ->when($venueId, fn ($query) => $query->where('venue_id', $venueId))
            ->latest('id')
            ->first()
            ?? Player::where('user_id', $user->id)->latest('id')->first();

        if ($request->hasFile('avatar')) {
            $old = $user->avatar;
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = '/storage/' . $path;

            if ($old) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $old));
            }
        }

        unset($data['avatar']);
        $profileData = [
            'phone' => $data['phone'] ?? null,
            'birthday' => $data['birthday'] ?? null,
            'address' => $data['address'] ?? null,
        ];
        $socialData = [
            'facebook_url' => $data['facebook_url'] ?? null,
            'instagram_url' => $data['instagram_url'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'social_links' => $data['social_links'] ?? null,
        ];

        unset($data['phone'], $data['birthday'], $data['address'], $data['facebook_url'], $data['instagram_url'], $data['website_url'], $data['social_links']);
        $data['name'] = $fullName;
        $user->fill($data);
        $user->fill($socialData);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
        $user->playerProfiles()->update([
            'name' => $fullName,
            'full_name' => $fullName,
            ...$profileData,
        ]);

        if (!$playerProfile && $venueId) {
            $playerProfile = Player::create([
                'user_id' => $user->id,
                'venue_id' => $venueId,
                'name' => $fullName,
                'full_name' => $fullName,
                ...$profileData,
                'show_in_roster' => true,
            ]);
        } elseif ($playerProfile) {
            $playerProfile->update([
                'name' => $fullName,
                'full_name' => $fullName,
                ...$profileData,
            ]);
        }

        return to_route('profile.edit')->with('status', 'profile-updated');
    }

    public function updateAllTimeStatsVisibility(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'field' => ['required', 'string', 'in:' . implode(',', self::ALL_TIME_STATS_VISIBILITY_FIELDS)],
            'visible' => ['required', 'boolean'],
        ]);

        $visibleFields = collect($this->allTimeStatsVisibleFields($user));

        if ($validated['visible']) {
            $visibleFields->push($validated['field']);
        } else {
            $visibleFields = $visibleFields->reject(fn ($field) => $field === $validated['field']);
        }

        $user->update([
            'all_time_stats_visible_fields' => $visibleFields->unique()->values()->all(),
        ]);

        return response()->json([
            'success' => true,
            'visible_fields' => $this->allTimeStatsVisibleFields($user->fresh()),
        ]);
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
