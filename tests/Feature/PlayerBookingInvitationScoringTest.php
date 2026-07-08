<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Player;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerBookingInvitationScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_invited_player_gets_view_only_scoring_access(): void
    {
        $venue = Venue::create([
            'name' => 'Invite Court',
            'scheduler_id' => null,
            'court_count' => 2,
            'opening_time' => '08:00',
            'closing_time' => '22:00',
            'default_hourly_rate' => 180,
            'is_active' => true,
        ]);

        $owner = User::factory()->create([
            'role' => 'player',
            'email_verified_at' => now(),
        ]);
        $invitee = User::factory()->create([
            'role' => 'player',
            'email_verified_at' => now(),
        ]);

        $ownerPlayer = Player::create([
            'user_id' => $owner->id,
            'venue_id' => $venue->id,
            'name' => 'Owner Player',
            'full_name' => 'Owner Player',
            'show_in_roster' => true,
        ]);
        $inviteePlayer = Player::create([
            'user_id' => $invitee->id,
            'venue_id' => $venue->id,
            'name' => 'Invitee Player',
            'full_name' => 'Invitee Player',
            'show_in_roster' => true,
        ]);
        $playerThree = Player::create([
            'venue_id' => $venue->id,
            'name' => 'Player Three',
            'full_name' => 'Player Three',
            'show_in_roster' => true,
        ]);
        $playerFour = Player::create([
            'venue_id' => $venue->id,
            'name' => 'Player Four',
            'full_name' => 'Player Four',
            'show_in_roster' => true,
        ]);

        $booking = Booking::create([
            'user_id' => $owner->id,
            'venue_id' => $venue->id,
            'booking_date' => now()->toDateString(),
            'start_time' => now()->copy()->subMinutes(15)->format('H:i:s'),
            'end_time' => now()->copy()->addMinutes(45)->format('H:i:s'),
            'cost_per_hour' => 180,
            'total_cost' => 180,
            'lead_name' => 'Owner Booker',
            'lead_address' => 'Court Address',
            'player_count' => 4,
            'court_number' => 1,
            'client_type' => 'member',
            'status' => 'approved',
            'type' => 'booking',
        ]);

        $booking->players()->attach($ownerPlayer->id, ['status' => 'accepted']);
        $booking->players()->attach($inviteePlayer->id, ['status' => 'accepted', 'invited_by_user_id' => $owner->id, 'responded_at' => now()]);
        $booking->players()->attach($playerThree->id, ['status' => 'accepted']);
        $booking->players()->attach($playerFour->id, ['status' => 'accepted']);

        $this->actingAs($invitee)
            ->get(route('scoring'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Scoring')
                ->where('settings.player_scoring_mode', true)
                ->where('settings.player_scoring_view_only', true)
                ->where('playerBooking.id', $booking->id)
            );

        $this->actingAs($invitee)
            ->post(route('matches.store'), [
                'player_1_id' => $ownerPlayer->id,
                'player_2_id' => $inviteePlayer->id,
                'player_3_id' => $playerThree->id,
                'player_4_id' => $playerFour->id,
                'player_1_score' => 11,
                'player_2_score' => 7,
                'match_date' => now()->toDateString(),
                'booking_id' => $booking->id,
            ])
            ->assertForbidden();
    }
}
