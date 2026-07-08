<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BestOfNTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    private function makeTournament(array $overrides = []): Tournament
    {
        return Tournament::create(array_merge([
            'name' => 'BoN Cup',
            'type' => 'single_elimination',
            'category' => 'mens',
            'min_players' => 4,
            'max_players' => 4,
            'best_of' => 1,
            'start_time' => '08:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => false,
        ], $overrides));
    }

    private function makeMatch(Tournament $tournament): TournamentMatch
    {
        $tp1 = TournamentPlayer::create(['tournament_id' => $tournament->id, 'player1_name' => 'Team A1', 'player2_name' => 'Team A2', 'seed' => 1]);
        $tp2 = TournamentPlayer::create(['tournament_id' => $tournament->id, 'player1_name' => 'Team B1', 'player2_name' => 'Team B2', 'seed' => 2]);

        return TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 1,
            'match_order' => 1,
            'bracket' => 'winners',
            'team1_id' => $tp1->id,
            'team2_id' => $tp2->id,
        ]);
    }

    public function test_can_create_tournament_with_best_of_3()
    {
        $this->actingAdmin();

        $response = $this->post(route('tournaments.store'), [
            'name' => 'Bo3 Cup',
            'type' => 'single_elimination',
            'category' => 'mens',
            'min_players' => 4,
            'max_players' => 8,
            'best_of' => 3,
            'start_time' => '08:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => false,
        ]);

        $response->assertRedirect();
        $t = Tournament::where('name', 'Bo3 Cup')->first();
        $this->assertNotNull($t);
        $this->assertEquals(3, (int) $t->best_of);
    }

    public function test_can_create_tournament_with_best_of_5()
    {
        $this->actingAdmin();

        $response = $this->post(route('tournaments.store'), [
            'name' => 'Bo5 Cup',
            'type' => 'single_elimination',
            'category' => 'mens',
            'min_players' => 4,
            'max_players' => 8,
            'best_of' => 5,
            'start_time' => '08:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => false,
        ]);

        $response->assertRedirect();
        $t = Tournament::where('name', 'Bo5 Cup')->first();
        $this->assertNotNull($t);
        $this->assertEquals(5, (int) $t->best_of);
    }

    public function test_best_of_must_be_in_valid_values()
    {
        $this->actingAdmin();

        foreach ([2, 4, 7, 0, -1] as $invalid) {
            $response = $this->post(route('tournaments.store'), [
                'name' => 'Invalid BoN',
                'type' => 'single_elimination',
                'category' => 'mens',
                'min_players' => 4,
                'max_players' => 8,
                'best_of' => $invalid,
                'start_time' => '08:00',
                'match_duration' => 25,
                'rest_time' => 5,
                'enable_break' => false,
            ]);

            $response->assertSessionHasErrors(['best_of']);
        }
    }

    public function test_best_of_defaults_to_1_for_existing_tournaments()
    {
        $t = $this->makeTournament();

        $this->assertEquals(1, (int) $t->best_of);
    }

    public function test_can_update_tournament_best_of()
    {
        $this->actingAdmin();
        $t = $this->makeTournament();

        $response = $this->put(route('tournaments.update', $t->id), [
            'best_of' => 3,
        ]);

        $response->assertRedirect();
        $t->refresh();
        $this->assertEquals(3, (int) $t->best_of);
    }

    public function test_record_score_with_best_of_1_rejects_tie()
    {
        $this->actingAdmin();
        $t = $this->makeTournament(['best_of' => 1]);
        $match = $this->makeMatch($t);

        $response = $this->post(route('tournaments.record-score', $match->id), [
            'team1_score' => 11,
            'team2_score' => 11,
        ]);

        $response->assertSessionHasErrors(['error']);
    }

    public function test_record_score_with_best_of_3_rejects_score_below_games_needed()
    {
        $this->actingAdmin();
        $t = $this->makeTournament(['best_of' => 3]);
        $match = $this->makeMatch($t);

        $response = $this->post(route('tournaments.record-score', $match->id), [
            'team1_score' => 1,
            'team2_score' => 0,
        ]);

        $response->assertSessionHasErrors(['error']);
        $this->assertStringContainsString('2 games', session('errors')->first());
    }

    public function test_record_score_with_best_of_3_accepts_two_games_to_one()
    {
        $this->actingAdmin();
        $t = $this->makeTournament(['best_of' => 3]);
        $match = $this->makeMatch($t);

        $response = $this->post(route('tournaments.record-score', $match->id), [
            'team1_score' => 2,
            'team2_score' => 1,
        ]);

        $response->assertRedirect();
        $match->refresh();
        $this->assertEquals(2, $match->team1_score);
        $this->assertEquals(1, $match->team2_score);
        $this->assertEquals($match->team1_id, $match->winner_id);
    }

    public function test_record_score_with_best_of_3_rejects_one_to_zero()
    {
        $this->actingAdmin();
        $t = $this->makeTournament(['best_of' => 3]);
        $match = $this->makeMatch($t);

        $response = $this->post(route('tournaments.record-score', $match->id), [
            'team1_score' => 1,
            'team2_score' => 0,
        ]);

        $response->assertSessionHasErrors(['error']);
    }

    public function test_record_score_with_best_of_5_requires_three_games()
    {
        $this->actingAdmin();
        $t = $this->makeTournament(['best_of' => 5]);
        $match = $this->makeMatch($t);

        $response = $this->post(route('tournaments.record-score', $match->id), [
            'team1_score' => 2,
            'team2_score' => 1,
        ]);

        $response->assertSessionHasErrors(['error']);
        $this->assertStringContainsString('3 games', session('errors')->first());
    }

    public function test_record_score_with_best_of_5_accepts_three_to_two()
    {
        $this->actingAdmin();
        $t = $this->makeTournament(['best_of' => 5]);
        $match = $this->makeMatch($t);

        $response = $this->post(route('tournaments.record-score', $match->id), [
            'team1_score' => 3,
            'team2_score' => 2,
        ]);

        $response->assertRedirect();
        $match->refresh();
        $this->assertEquals(3, $match->team1_score);
        $this->assertEquals(2, $match->team2_score);
    }

    public function test_record_score_with_best_of_5_rejects_two_to_zero()
    {
        $this->actingAdmin();
        $t = $this->makeTournament(['best_of' => 5]);
        $match = $this->makeMatch($t);

        $response = $this->post(route('tournaments.record-score', $match->id), [
            'team1_score' => 2,
            'team2_score' => 0,
        ]);

        $response->assertSessionHasErrors(['error']);
    }

    public function test_tournament_index_payload_includes_best_of()
    {
        $this->actingAdmin();
        $t = $this->makeTournament(['best_of' => 3]);

        $response = $this->get(route('tournaments.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tournament')
            ->has('tournaments', 1)
            ->where('tournaments.0.best_of', 3)
        );
    }
}
