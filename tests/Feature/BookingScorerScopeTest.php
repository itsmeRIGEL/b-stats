<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingScorerScopeTest extends TestCase
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

        $scheduler->update([
            'venue_id' => $venue->id,
        ]);

        return $scheduler->fresh();
    }

    public function test_scheduler_only_sees_owned_scorers_in_bookings_picker()
    {
        $scheduler = $this->createSchedulerWithVenue('eugine@example.com');
        $otherScheduler = $this->createSchedulerWithVenue('other@example.com');

        $ownedScorer = User::factory()->create([
            'name' => 'Owned Scorer',
            'role' => 'scorer',
            'scheduler_id' => $scheduler->id,
            'venue_id' => $scheduler->venue_id,
        ]);

        User::factory()->create([
            'name' => 'Hannah',
            'role' => 'scorer',
            'scheduler_id' => $otherScheduler->id,
            'venue_id' => $scheduler->venue_id,
        ]);

        $this->actingAs($scheduler);

        $response = $this->get(route('bookings'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Bookings')
            ->has('scorers', 1)
            ->where('scorers.0.id', $ownedScorer->id)
            ->where('scorers.0.name', 'Owned Scorer')
        );
    }

    public function test_scheduler_cannot_assign_foreign_scorer_to_court()
    {
        $scheduler = $this->createSchedulerWithVenue('eugine@example.com');
        $otherScheduler = $this->createSchedulerWithVenue('other@example.com');

        $foreignScorer = User::factory()->create([
            'name' => 'Hannah',
            'role' => 'scorer',
            'scheduler_id' => $otherScheduler->id,
            'venue_id' => $scheduler->venue_id,
        ]);

        $this->actingAs($scheduler);

        $response = $this->post(route('court-assignments.save'), [
            'court_number' => 1,
            'scorer_id' => $foreignScorer->id,
            'assignment_date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('scorer_id');
        $this->assertDatabaseMissing('court_scorer_assignments', [
            'court_number' => 1,
            'scorer_id' => $foreignScorer->id,
        ]);
    }

    public function test_scheduler_cannot_assign_foreign_scorer_when_creating_booking()
    {
        $scheduler = $this->createSchedulerWithVenue('eugine@example.com');
        $otherScheduler = $this->createSchedulerWithVenue('other@example.com');

        $foreignScorer = User::factory()->create([
            'name' => 'Hannah',
            'role' => 'scorer',
            'scheduler_id' => $otherScheduler->id,
            'venue_id' => $scheduler->venue_id,
        ]);

        $this->actingAs($scheduler);

        $response = $this->post(route('bookings.store'), [
            'booking_date' => now()->toDateString(),
            'start_time' => '10:00',
            'duration_hours' => 1,
            'cost_per_hour' => 100,
            'lead_name' => 'Lead Booker',
            'lead_address' => 'Address',
            'guest_phone' => '123456789',
            'player_count' => 4,
            'court_number' => 1,
            'client_type' => 'member',
            'scorer_id' => $foreignScorer->id,
            'type' => 'booking',
        ]);

        $response->assertSessionHasErrors('scorer_id');
        $this->assertDatabaseMissing('bookings', [
            'lead_name' => 'Lead Booker',
            'scorer_id' => $foreignScorer->id,
        ]);
    }
}
