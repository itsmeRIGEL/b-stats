<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentDay;
use App\Models\TournamentSubFolder;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentIsolationValidationTest extends TestCase
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

    public function test_scheduler_cannot_create_tournament_using_foreign_subfolder()
    {
        $scheduler = $this->createSchedulerWithVenue('eugine@example.com');
        $otherScheduler = $this->createSchedulerWithVenue('other@example.com');

        $foreignDay = TournamentDay::create([
            'name' => 'Foreign Day',
            'date' => now()->toDateString(),
            'status' => 'active',
            'venue_id' => $otherScheduler->venue_id,
        ]);

        $foreignSubFolder = TournamentSubFolder::create([
            'name' => 'Foreign Folder',
            'tournament_day_id' => $foreignDay->id,
            'venue_id' => $otherScheduler->venue_id,
        ]);

        $this->actingAs($scheduler);

        $response = $this->post(route('tournaments.store'), [
            'name' => 'Blocked Tournament',
            'type' => 'single_elimination',
            'category' => 'mens',
            'min_players' => 4,
            'max_players' => 8,
            'best_of' => 1,
            'start_time' => '08:00',
            'match_duration' => 20,
            'rest_time' => 5,
            'enable_break' => false,
            'tournament_sub_folder_id' => $foreignSubFolder->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('tournaments', [
            'name' => 'Blocked Tournament',
        ]);
    }

    public function test_scheduler_bulk_assign_day_only_updates_owned_tournaments()
    {
        $scheduler = $this->createSchedulerWithVenue('eugine@example.com');
        $otherScheduler = $this->createSchedulerWithVenue('other@example.com');

        $ownedTournament = Tournament::create([
            'name' => 'Owned Tournament',
            'type' => 'single_elimination',
            'category' => 'mens',
            'min_players' => 4,
            'max_players' => 8,
            'best_of' => 1,
            'start_time' => '08:00',
            'match_duration' => 20,
            'rest_time' => 5,
            'enable_break' => false,
            'venue_id' => $scheduler->venue_id,
        ]);

        $foreignTournament = Tournament::create([
            'name' => 'Foreign Tournament',
            'type' => 'single_elimination',
            'category' => 'mens',
            'min_players' => 4,
            'max_players' => 8,
            'best_of' => 1,
            'start_time' => '08:00',
            'match_duration' => 20,
            'rest_time' => 5,
            'enable_break' => false,
            'venue_id' => $otherScheduler->venue_id,
        ]);

        $ownedDay = TournamentDay::create([
            'name' => 'Owned Day',
            'date' => now()->toDateString(),
            'status' => 'active',
            'venue_id' => $scheduler->venue_id,
        ]);

        $this->actingAs($scheduler);

        $response = $this->post(route('tournaments.bulk-assign-day'), [
            'tournament_ids' => [$ownedTournament->id, $foreignTournament->id],
            'tournament_day_id' => $ownedDay->id,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('tournaments', [
            'id' => $ownedTournament->id,
            'tournament_day_id' => $ownedDay->id,
        ]);

        $this->assertDatabaseHas('tournaments', [
            'id' => $foreignTournament->id,
            'tournament_day_id' => null,
            'venue_id' => $otherScheduler->venue_id,
        ]);
    }
}
