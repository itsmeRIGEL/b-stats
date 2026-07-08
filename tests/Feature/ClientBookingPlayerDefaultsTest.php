<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Player;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientBookingPlayerDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_player_booking_uses_account_details_and_membership_status(): void
    {
        $player = User::factory()->create([
            'name' => 'Wrong Display Name',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'role' => 'player',
            'email_verified_at' => now(),
        ]);

        $venue = Venue::create([
            'name' => 'Toolbar Court',
            'scheduler_id' => null,
            'court_count' => 3,
            'opening_time' => '08:00',
            'closing_time' => '22:00',
            'default_hourly_rate' => 180,
            'member_booking_fee' => 180,
            'non_member_booking_fee' => 220,
            'is_active' => true,
        ]);

        Player::create([
            'user_id' => $player->id,
            'venue_id' => $venue->id,
            'name' => 'Old Player Name',
            'full_name' => 'Old Player Name',
            'phone' => '09171234567',
            'address' => 'Cagayan de Oro',
            'is_member' => true,
        ]);

        $this->actingAs($player)
            ->post(route('book.venue.store', ['venue' => $venue->name]), [
                'booking_date' => now()->addDay()->toDateString(),
                'start_time' => '10:00',
                'end_time' => '11:00',
                'cost_per_hour' => 999,
                'total_cost' => 999,
                'lead_name' => 'Manual Override Name',
                'lead_address' => 'Manual Address',
                'guest_email' => 'manual@example.com',
                'guest_phone' => '09999999999',
                'player_count' => 4,
                'court_number' => 1,
                'client_type' => 'non_member',
            ])
            ->assertRedirect();

        $booking = Booking::firstOrFail();

        $this->assertSame('Juan Dela Cruz', $booking->lead_name);
        $this->assertSame('Cagayan de Oro', $booking->lead_address);
        $this->assertSame('juan@example.com', $booking->guest_email);
        $this->assertSame('09171234567', $booking->guest_phone);
        $this->assertSame('member', $booking->client_type);
        $this->assertSame($player->id, $booking->user_id);
        $this->assertSame($venue->id, $booking->venue_id);
    }
}
