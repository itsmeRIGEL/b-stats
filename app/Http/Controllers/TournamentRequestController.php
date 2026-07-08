<?php

namespace App\Http\Controllers;

use App\Models\Booking;
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

        return Inertia::render('PlayerVenues', [
            'venues' => Venue::query()
                ->whereNotNull('scheduler_id')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(function (Venue $venue) {
                    $amenities = collect($venue->amenities ?? [])
                        ->map(static fn ($item) => trim((string) $item))
                        ->filter()
                        ->values()
                        ->all();

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
                        'amenities' => $amenities,
                        'default_hourly_rate' => (float) ($venue->default_hourly_rate ?? 0),
                        'contact_phone' => $venue->contact_phone,
                    ];
                }),
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
            'preferred_start_time' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:2000',
            'request_type' => 'nullable|in:new_tournament,edit_access',
            'tournament_id' => 'nullable|exists:tournaments,id',
        ]);

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
            ->get();

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
