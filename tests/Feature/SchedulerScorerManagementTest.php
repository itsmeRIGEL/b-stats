<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerScorerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_can_only_create_scorer_accounts_linked_to_the_scheduler()
    {
        $scheduler = User::factory()->create(['role' => 'scheduler']);
        $venue = Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Eugine Venue',
            'court_count' => 4,
            'is_active' => true,
        ]);
        $scheduler->update(['venue_id' => $venue->id]);

        $this->actingAs($scheduler);

        $response = $this->post(route('admin-users.store'), [
            'name' => 'Scorer One',
            'email' => 'scorer-one@example.com',
            'role' => 'scorer',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'scorer-one@example.com',
            'role' => 'scorer',
            'scheduler_id' => $scheduler->id,
            'venue_id' => $venue->id,
        ]);
    }

    public function test_scheduler_cannot_create_another_scheduler_account()
    {
        $scheduler = User::factory()->create(['role' => 'scheduler']);
        $venue = Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Scheduler Venue',
            'court_count' => 4,
            'is_active' => true,
        ]);
        $scheduler->update(['venue_id' => $venue->id]);

        $this->actingAs($scheduler);

        $response = $this->post(route('admin-users.store'), [
            'name' => 'Another Scheduler',
            'email' => 'scheduler-two@example.com',
            'role' => 'scheduler',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', [
            'email' => 'scheduler-two@example.com',
        ]);
    }

    public function test_scheduler_cannot_edit_a_scorer_owned_by_another_scheduler()
    {
        $scheduler = User::factory()->create(['role' => 'scheduler']);
        $venue = Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Scheduler Venue',
            'court_count' => 4,
            'is_active' => true,
        ]);
        $scheduler->update(['venue_id' => $venue->id]);

        $otherScheduler = User::factory()->create(['role' => 'scheduler']);
        $foreignScorer = User::factory()->create([
            'role' => 'scorer',
            'scheduler_id' => $otherScheduler->id,
        ]);

        $this->actingAs($scheduler);

        $response = $this->put(route('admin-users.update', $foreignScorer), [
            'name' => 'Updated Name',
            'email' => $foreignScorer->email,
            'role' => 'scorer',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertForbidden();
    }
}
