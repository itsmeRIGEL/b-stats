<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringTemporaryPlayersTest extends TestCase
{
    use RefreshDatabase;

    private function createSchedulerWithVenue(): User
    {
        $scheduler = User::factory()->create([
            'role' => 'scheduler',
        ]);

        $venue = Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Scoring Venue',
            'court_count' => 4,
            'is_active' => true,
        ]);

        $scheduler->update([
            'venue_id' => $venue->id,
        ]);

        return $scheduler->fresh();
    }

    public function test_scoring_bulk_add_still_rejects_unregistered_players_from_roster_session(): void
    {
        $scheduler = $this->createSchedulerWithVenue();

        $this->actingAs($scheduler);

        $response = $this->post(route('players.bulk-session'), [
            'names' => ['Unknown One'],
        ]);

        $response->assertSessionHasErrors('names');
        $this->assertDatabaseMissing('players', [
            'name' => 'Unknown One',
        ]);
    }

    public function test_match_can_be_saved_with_temporary_players_without_creating_player_records(): void
    {
        $scheduler = $this->createSchedulerWithVenue();
        $venueId = $scheduler->venue_id;

        $registeredOne = Player::create([
            'name' => 'Registered One',
            'venue_id' => $venueId,
        ]);
        $registeredTwo = Player::create([
            'name' => 'Registered Two',
            'venue_id' => $venueId,
        ]);

        $this->actingAs($scheduler);

        $response = $this->post(route('matches.store'), [
            'player_1_id' => $registeredOne->id,
            'player_2_name' => 'Guest B',
            'player_3_id' => $registeredTwo->id,
            'player_4_name' => 'Guest D',
            'player_1_score' => 11,
            'player_2_score' => 8,
            'match_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();

        $match = GameMatch::first();

        $this->assertNotNull($match);
        $this->assertSame($registeredOne->id, $match->player_1_id);
        $this->assertSame('Guest B', $match->player_2_name);
        $this->assertSame($registeredTwo->id, $match->player_3_id);
        $this->assertSame('Guest D', $match->player_4_name);

        $this->assertDatabaseMissing('players', [
            'name' => 'Guest B',
        ]);
        $this->assertDatabaseMissing('players', [
            'name' => 'Guest D',
        ]);
    }

    public function test_save_session_tallies_only_registered_players_when_match_has_temporary_players(): void
    {
        $scheduler = $this->createSchedulerWithVenue();
        $venueId = $scheduler->venue_id;

        $winnerA = Player::create([
            'name' => 'Winner A',
            'venue_id' => $venueId,
            'show_in_roster' => true,
        ]);
        $winnerB = Player::create([
            'name' => 'Winner B',
            'venue_id' => $venueId,
            'show_in_roster' => true,
        ]);

        $match = GameMatch::create([
            'player_1_id' => $winnerA->id,
            'player_1_name' => null,
            'player_2_id' => null,
            'player_2_name' => 'Guest B',
            'player_3_id' => $winnerB->id,
            'player_3_name' => null,
            'player_4_id' => null,
            'player_4_name' => 'Guest D',
            'player_1_score' => 11,
            'player_2_score' => 7,
            'match_date' => now()->toDateString(),
            'is_tallied' => false,
            'venue_id' => $venueId,
        ]);

        $this->actingAs($scheduler);
        $response = $this->post(route('scoring.save'));

        $response->assertRedirect();

        $this->assertSame(1, $winnerA->fresh()->wins);
        $this->assertSame(1, $winnerA->fresh()->total_matches);
        $this->assertSame(1, $winnerB->fresh()->wins);
        $this->assertSame(1, $winnerB->fresh()->total_matches);
        $this->assertEquals(1, $match->fresh()->is_tallied);
        $this->assertDatabaseMissing('players', [
            'name' => 'Guest B',
        ]);
    }
}
