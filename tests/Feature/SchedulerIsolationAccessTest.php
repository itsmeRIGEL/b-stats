<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerIsolationAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createSchedulerWithVenue(string $email): User
    {
        $scheduler = User::factory()->create([
            'role' => 'scheduler',
            'email' => $email,
        ]);

        $venue = Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Venue for ' . $scheduler->name,
            'court_count' => 4,
            'is_active' => true,
        ]);

        $scheduler->update(['venue_id' => $venue->id]);

        return $scheduler->fresh();
    }

    public function test_scorer_cannot_access_scheduler_bookings_page()
    {
        $scheduler = $this->createSchedulerWithVenue('scheduler@example.com');
        $scorer = User::factory()->create([
            'role' => 'scorer',
            'scheduler_id' => $scheduler->id,
            'venue_id' => $scheduler->venue_id,
        ]);

        $this->actingAs($scorer);

        $response = $this->get(route('bookings'));

        $response->assertRedirect(route('scoring'));
    }

    public function test_memberships_page_only_shows_players_from_scheduler_venue()
    {
        $scheduler = $this->createSchedulerWithVenue('eugine@example.com');
        $otherScheduler = $this->createSchedulerWithVenue('other@example.com');

        $ownedPlayer = Player::create([
            'name' => 'Owned Player',
            'show_in_roster' => true,
            'venue_id' => $scheduler->venue_id,
        ]);

        Player::create([
            'name' => 'Foreign Player',
            'show_in_roster' => true,
            'venue_id' => $otherScheduler->venue_id,
        ]);

        $this->actingAs($scheduler);

        $response = $this->get(route('memberships'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Memberships')
            ->has('players', 1)
            ->where('players.0.id', $ownedPlayer->id)
            ->where('players.0.name', 'Owned Player')
        );
    }
}
