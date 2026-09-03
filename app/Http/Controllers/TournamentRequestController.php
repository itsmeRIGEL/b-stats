<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\DateAvailability;
use App\Models\DayAvailability;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentDay;
use App\Models\TournamentRequest;
use App\Models\Venue;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TournamentRequestController extends Controller
{
    private function publicStorageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, '/storage/')) {
            return $path;
        }

        return '/storage/' . ltrim($path, '/');
    }

    private function scopedVenues()
    {
        $user = auth()->user();

        if (!$user || $user->isAdmin()) {
            return Venue::query();
        }

        if ($user->isScheduler()) {
            return Venue::where('scheduler_id', $user->id);
        }

        return Venue::query();
    }

    private function ensureRequestAccess(TournamentRequest $requestModel): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Access denied.');
        }

        if ($user->isAdmin()) {
            return;
        }

        if ($user->isScheduler() || in_array($user->role, ['scheduler', 'scheduler_scorer'], true)) {
            $venueId = $user->currentVenue()?->id;
            $ownsVenue = Venue::query()
                ->where('id', $requestModel->venue_id)
                ->where('scheduler_id', $user->id)
                ->exists();

            if (($venueId && (int) $requestModel->venue_id === (int) $venueId) || $ownsVenue) {
                return;
            }
        }

        if ($user->isPlayer() && (int) $requestModel->user_id === (int) $user->id) {
            return;
        }

        abort(403, 'Access denied.');
    }

    public function playerVenues()
    {
        $user = auth()->user();
        if (!$user || !$user->isPlayer()) {
            abort(403, 'Access denied.');
        }

        $primaryVenueId = Player::where('user_id', $user->id)
            ->whereNotNull('venue_id')
            ->latest('id')
            ->value('venue_id');

        $playedVenueIds = Player::where('user_id', $user->id)->whereNotNull('venue_id')->pluck('venue_id')->all();
        $bookedVenueIds = Booking::where('user_id', $user->id)->whereNotNull('venue_id')->pluck('venue_id')->all();
        $requestedVenueIds = TournamentRequest::where('user_id', $user->id)->whereNotNull('venue_id')->pluck('venue_id')->all();

        $allVisitedVenueIds = array_values(array_unique(array_filter(array_merge($playedVenueIds, $bookedVenueIds, $requestedVenueIds))));

        return Inertia::render('PlayerVenues', [
            'venues' => Venue::query()
                ->whereNotNull('scheduler_id')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(function (Venue $venue) use ($primaryVenueId, $allVisitedVenueIds) {
                    $amenities = collect($venue->amenities ?? [])
                        ->map(static fn ($item) => trim((string) $item))
                        ->filter()
                        ->values()
                        ->all();

                    $isPrimary = (int) $venue->id === (int) $primaryVenueId;
                    $isVisited = !$isPrimary && in_array($venue->id, $allVisitedVenueIds, true);

                    return [
                        'id' => $venue->id,
                        'name' => $venue->name,
                        'address' => $venue->address,
                        'tagline' => $venue->tagline,
                        'description' => $venue->description,
                        'court_count' => (int) $venue->court_count,
                        'covered_court_count' => $venue->covered_court_count ? (int) $venue->covered_court_count : null,
                        'cover_photo_url' => $this->publicStorageUrl($venue->cover_photo_path),
                        'logo_url' => $this->publicStorageUrl($venue->logo_path),
                        'gallery_urls' => $venue->gallery_urls ?? [],
                        'amenities' => $amenities,
                        'default_hourly_rate' => (float) ($venue->default_hourly_rate ?? 0),
                        'contact_phone' => $venue->contact_phone,
                        'payment_account_name' => $venue->payment_account_name,
                        'payment_qr_photo' => $this->publicStorageUrl($venue->payment_qr_photo),
                        'is_primary' => $isPrimary,
                        'is_visited' => $isVisited,
                    ];
                })
                ->sort(function ($a, $b) {
                    if ($a['is_primary'] !== $b['is_primary']) {
                        return $a['is_primary'] ? -1 : 1;
                    }
                    if ($a['is_visited'] !== $b['is_visited']) {
                        return $a['is_visited'] ? -1 : 1;
                    }
                    return strnatcasecmp($a['name'], $b['name']);
                })
                ->values()
                ->all(),
            'requests' => TournamentRequest::with(['venue:id,name', 'tournament:id,name,status', 'tournamentDay:id,name,date,status'])
                ->where('user_id', $user->id)
                ->latest()
                ->get(),
            'bookings' => Booking::with(['venue:id,name', 'user:id,name,username', 'players.user:id,username,name'])
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->orWhereHas('players', function ($playerQuery) use ($user) {
                            $playerQuery->where('players.user_id', $user->id)
                                ->where(function ($statusQuery) {
                                    $statusQuery->where('booking_player.status', 'accepted')
                                        ->orWhere('booking_player.status', 'confirmed')
                                        ->orWhereNull('booking_player.status');
                                });
                        });
                })
                ->where('type', 'booking')
                ->orderBy('booking_date')
                ->orderBy('start_time')
                ->get()
                ->map(fn (Booking $booking) => [
                    'id' => $booking->id,
                    'venue_name' => $booking->venue?->name,
                    'booking_date' => $booking->booking_date,
                    'start_time' => $booking->start_time,
                    'end_time' => $booking->end_time,
                    'court_number' => $booking->court_number,
                    'player_count' => $booking->player_count,
                    'status' => $booking->status,
                    'payment_status' => $booking->payment_status,
                    'player_username' => $booking->players->firstWhere('user_id', $user->id)?->user?->username
                        ?: $booking->user?->username
                        ?: $booking->lead_name,
                    'total_cost' => (float) $booking->total_cost,
                    'client_type' => $booking->client_type,
                ]),
        ]);
    }

    public function setPrimaryVenue(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->isPlayer()) {
            abort(403, 'Access denied.');
        }

        $validated = $request->validate([
            'venue_id' => 'required|exists:venues,id',
        ]);

        // Set favourite/primary venue preference on user model
        $user->update(['venue_id' => $validated['venue_id']]);

        // Ensure a player profile exists for this venue without overwriting existing venue profiles
        $existing = Player::where('user_id', $user->id)->where('venue_id', $validated['venue_id'])->first();
        if (!$existing) {
            $basePlayer = Player::where('user_id', $user->id)->first();
            Player::create([
                'user_id' => $user->id,
                'venue_id' => $validated['venue_id'],
                'name' => $basePlayer?->name ?? $user->name,
                'full_name' => $basePlayer?->full_name ?? $user->name,
                'phone' => $basePlayer?->phone ?? $user->phone,
                'birthday' => $basePlayer?->birthday,
                'address' => $basePlayer?->address ?? $user->address,
                'show_in_roster' => true,
            ]);
        }

        return back()->with('success', 'Primary venue updated successfully.');
    }

    public function venueAvailability(Request $request)
    {
        $venueId = $request->input('venue_id');
        $date = $request->input('date');

        if (!$venueId || !$date) {
            return response()->json(['error' => 'Missing venue_id or date'], 400);
        }

        $venue = Venue::find($venueId);
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $avail = $this->getVenueDateAvailability($date, $venue->id);

        $bookings = Booking::where('venue_id', $venue->id)
            ->where('booking_date', $date)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->get(['id', 'start_time', 'end_time', 'court_number', 'status']);

        return response()->json([
            'is_closed' => $avail['is_closed'],
            'opening_time' => $avail['opening_time'],
            'closing_time' => $avail['closing_time'],
            'close_reason' => $avail['close_reason'],
            'court_count' => (int) $venue->court_count,
            'bookings' => $bookings,
        ]);
    }

    private function getVenueDateAvailability($date, $venueId)
    {
        // 1. Check for specific date override
        $dateOverride = DateAvailability::where('date', $date)
            ->where('venue_id', $venueId)
            ->first();
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
        $daySetting = DayAvailability::where('day_of_week', $dayOfWeek)
            ->where('venue_id', $venueId)
            ->first();
        if ($daySetting && $daySetting->is_enabled) {
            return [
                'is_closed' => (bool)$daySetting->is_closed,
                'opening_time' => $daySetting->opening_time ? substr($daySetting->opening_time, 0, 5) : null,
                'closing_time' => $daySetting->closing_time ? substr($daySetting->closing_time, 0, 5) : null,
                'close_reason' => $daySetting->close_reason,
            ];
        }

        // 3. Fallback to venue settings
        $venue = Venue::find($venueId);
        return [
            'is_closed' => false,
            'opening_time' => $venue?->opening_time ? substr($venue->opening_time, 0, 5) : '08:00',
            'closing_time' => $venue?->closing_time ? substr($venue->closing_time, 0, 5) : '22:00',
            'close_reason' => null,
        ];
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->isPlayer()) {
            abort(403, 'Access denied.');
        }

        $validated = $request->validate([
            'venue_id' => 'required|exists:venues,id',
            'name' => 'required|string|max:255',
            'category' => 'nullable|in:mens,female,mix',
            'preferred_date' => 'nullable|date',
            'preferred_start_time' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'request_type' => 'nullable|in:new_tournament,edit_access',
            'tournament_id' => 'nullable|exists:tournaments,id',
            'total_cost' => 'nullable|numeric|min:0',
            'receipt_photo' => 'nullable|image|max:5120',
        ]);

        if (!empty($validated['preferred_date'])) {
            $today = now()->format('Y-m-d');
            if ($validated['preferred_date'] < $today) {
                return redirect()->back()->withErrors([
                    'preferred_date' => 'The preferred date cannot be in the past.',
                ]);
            }

            $avail = $this->getVenueDateAvailability($validated['preferred_date'], $validated['venue_id']);
            if ($avail['is_closed']) {
                return redirect()->back()->withErrors([
                    'preferred_date' => 'The venue is closed on this date' . ($avail['close_reason'] ? ': ' . $avail['close_reason'] : '.'),
                ]);
            }
        }

        $requestType = $validated['request_type'] ?? 'new_tournament';
        $targetTournament = null;

        if ($requestType === 'edit_access') {
            $targetTournament = Tournament::findOrFail($validated['tournament_id'] ?? null);

            if ((int) $targetTournament->manager_user_id !== (int) $user->id) {
                abort(403, 'Access denied.');
            }

            if ((int) $targetTournament->venue_id !== (int) $validated['venue_id']) {
                return redirect()->back()->withErrors([
                    'tournament_id' => 'The selected tournament does not belong to this venue.',
                ]);
            }

            $existingPendingRequest = TournamentRequest::query()
                ->where('user_id', $user->id)
                ->where('tournament_id', $targetTournament->id)
                ->where('request_type', 'edit_access')
                ->where('status', 'pending')
                ->exists();

            if ($existingPendingRequest) {
                return redirect()->back()->withErrors([
                    'notes' => 'You already have a pending edit access request for this tournament.',
                ]);
            }
        }

        $receiptPath = null;
        if ($request->hasFile('receipt_photo')) {
            $receiptPath = $request->file('receipt_photo')->store('receipts', 'public');
        }

        TournamentRequest::create([
            'user_id' => $user->id,
            'venue_id' => $validated['venue_id'],
            'name' => $requestType === 'edit_access' ? ($targetTournament?->name ?? $validated['name']) : $validated['name'],
            'category' => $requestType === 'edit_access'
                ? ($targetTournament?->category ?? ($validated['category'] ?? 'mens'))
                : ($validated['category'] ?? 'mens'),
            'preferred_date' => $validated['preferred_date'] ?? null,
            'preferred_start_time' => $validated['preferred_start_time'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'receipt_photo' => $receiptPath,
            'total_cost' => $validated['total_cost'] ?? null,
            'payment_status' => $receiptPath ? 'paid' : 'unpaid',
            'request_type' => $requestType,
            'status' => 'pending',
            'tournament_id' => $targetTournament?->id,
        ]);

        return redirect()->back()->with('success', $requestType === 'edit_access'
            ? 'Edit access request submitted. The venue scheduler can review it now.'
            : 'Tournament request submitted. The venue scheduler can review it now.');
    }

    public function index()
    {
        $user = auth()->user();
        if (!$user || (!$user->isAdmin() && !$user->isScheduler())) {
            abort(403, 'Access denied.');
        }

        $requests = TournamentRequest::with([
                'user:id,name,username,email',
                'venue:id,name,address',
                'approver:id,name',
                'tournament:id,name,status',
                'tournamentDay:id,name,date,status',
            ])
            ->when(!$user->isAdmin(), function ($query) use ($user) {
                $query->where('venue_id', $user->currentVenue()?->id);
            })
            ->latest()
            ->get()
            ->map(function ($tr) {
                $tr->receipt_url = $tr->receipt_photo ? $this->publicStorageUrl($tr->receipt_photo) : null;
                return $tr;
            });

        return Inertia::render('TournamentRequests', [
            'requests' => $requests,
        ]);
    }

    public function approve(TournamentRequest $requestModel)
    {
        $this->ensureRequestAccess($requestModel);

        if ($requestModel->status !== 'pending') {
            return redirect()->back()->with('error', 'This tournament request has already been processed.');
        }

        $tournament = null;
        $day = null;

        if (($requestModel->request_type ?? 'new_tournament') === 'edit_access' && $requestModel->tournament_id) {
            $tournament = Tournament::findOrFail($requestModel->tournament_id);

            if ((int) $tournament->manager_user_id !== (int) $requestModel->user_id) {
                return redirect()->back()->with('error', 'This player does not manage the requested tournament.');
            }

            if ((int) $tournament->venue_id !== (int) $requestModel->venue_id) {
                return redirect()->back()->with('error', 'This tournament does not belong to the current venue.');
            }

            $day = $tournament->tournamentDay;
            if ($day) {
                $day->update(['status' => 'active']);
            }
        } else {
            $day = TournamentDay::create([
                'name' => $requestModel->name,
                'date' => $requestModel->preferred_date?->format('Y-m-d') ?? now()->toDateString(),
                'status' => 'active',
                'assigned_courts' => null,
                'venue_id' => $requestModel->venue_id,
            ]);
        }

        $requestModel->update([
            'status' => 'approved',
            'approved_by_user_id' => auth()->id(),
            'approved_at' => now(),
            'tournament_id' => $tournament?->id,
            'tournament_day_id' => $day?->id ?? $tournament?->tournament_day_id,
            'rejection_reason' => null,
        ]);

        return redirect()->back()->with('success', ($requestModel->request_type ?? 'new_tournament') === 'edit_access'
            ? 'Edit access approved and the player workspace is unlocked.'
            : 'Tournament request approved and the player main folder is ready.');
    }

    public function reject(Request $request, TournamentRequest $requestModel)
    {
        $this->ensureRequestAccess($requestModel);

        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $requestModel->update([
            'status' => 'rejected',
            'approved_by_user_id' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Tournament request rejected.');
    }
}
