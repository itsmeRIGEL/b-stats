<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_tournament_with_schedules_settings()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $response = $this->post(route('tournaments.store'), [
            'name' => 'Winter Cup',
            'type' => 'single_elimination',
            'category' => 'mens',
            'min_players' => 4,
            'max_players' => 8,
            'best_of' => 1,
            'start_time' => '08:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => true,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        $response->assertRedirect();
        
        $tournament = Tournament::first();
        $this->assertEquals('Winter Cup', $tournament->name);
        $this->assertEquals('08:00', $tournament->start_time);
        $this->assertEquals(25, $tournament->match_duration);
        $this->assertEquals(5, $tournament->rest_time);
        $this->assertTrue($tournament->enable_break);
        $this->assertEquals('12:00', $tournament->break_start);
        $this->assertEquals('13:00', $tournament->break_end);
    }

    public function test_cannot_create_tournament_if_break_end_time_is_not_after_start_time()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $response = $this->post(route('tournaments.store'), [
            'name' => 'Winter Cup 2',
            'type' => 'single_elimination',
            'category' => 'mens',
            'min_players' => 4,
            'max_players' => 8,
            'best_of' => 1,
            'start_time' => '08:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => true,
            'break_start' => '12:00',
            'break_end' => '11:00',
        ]);

        $response->assertSessionHasErrors(['break_end']);
    }

    public function test_automatically_generates_schedules_respecting_duration_rest_and_breaks()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $tournament = Tournament::create([
            'name' => 'Spring Cup',
            'type' => 'single_elimination',
            'min_players' => 4,
            'max_players' => 4,
            'start_time' => '11:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => true,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        // Add 4 teams
        for ($i = 1; $i <= 4; $i++) {
            TournamentPlayer::create([
                'tournament_id' => $tournament->id,
                'player1_name' => "Player A{$i}",
                'player2_name' => "Player B{$i}",
                'seed' => $i,
            ]);
        }

        // Generate bracket (creates matches and generates schedules)
        $response = $this->post(route('tournaments.generate', $tournament->id));
        $response->assertRedirect();

        $matches = $tournament->matches()
            ->orderBy('round')
            ->orderByRaw("CASE WHEN bracket = 'winners' THEN 1 WHEN bracket = 'losers' THEN 2 WHEN bracket = 'grand_final' THEN 3 ELSE 4 END")
            ->orderBy('match_order')
            ->get();

        $this->assertCount(3, $matches); // 4 teams single elimination = 3 matches total

        // Match 1: starts at start_time (11:00)
        // Duration: 25 mins -> ends 11:25.
        // Rest: 5 mins -> Next candidate start: 11:30.
        $this->assertEquals('11:00', $matches[0]->scheduled_time);

        // Match 2: starts at 11:30.
        // Duration: 25 mins -> ends 11:55.
        // Rest: 5 mins -> Next candidate start: 12:00.
        $this->assertEquals('11:30', $matches[1]->scheduled_time);

        // Match 3: starts at 12:00 candidate start.
        // Duration: 25 mins -> ends 12:25.
        // Overlaps with break (12:00 to 13:00).
        // Automatically shifted to break_end: 13:00.
        $this->assertEquals('13:00', $matches[2]->scheduled_time);
    }

    public function test_double_elimination_scheduling_respects_dependencies()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $tournament = Tournament::create([
            'name' => 'DE Cup',
            'type' => 'double_elimination',
            'min_players' => 4,
            'max_players' => 4,
            'start_time' => '08:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => false,
        ]);

        for ($i = 1; $i <= 4; $i++) {
            TournamentPlayer::create([
                'tournament_id' => $tournament->id,
                'player1_name' => "P A{$i}",
                'player2_name' => "P B{$i}",
                'seed' => $i,
            ]);
        }

        $response = $this->post(route('tournaments.generate', $tournament->id));
        $response->assertRedirect();

        $matches = $tournament->matches()->get();

        $w1 = $matches->where('bracket', 'winners')->where('round', 1)->values();
        $this->assertCount(2, $w1);

        $w2 = $matches->where('bracket', 'winners')->where('round', 2)->first();
        $l1 = $matches->where('bracket', 'losers')->where('round', 1)->first();
        $l2 = $matches->where('bracket', 'losers')->where('round', 2)->first();
        $gf = $matches->where('bracket', 'grand_final')->first();

        $toMins = function($t) {
            $parts = explode(':', $t);
            return (int)$parts[0] * 60 + (int)$parts[1];
        };

        $w1_1_time = $toMins($w1[0]->scheduled_time);
        $w1_2_time = $toMins($w1[1]->scheduled_time);
        $l1_time = $toMins($l1->scheduled_time);

        $this->assertTrue($l1_time >= max($w1_1_time, $w1_2_time) + 25 + 5);

        $w2_time = $toMins($w2->scheduled_time);
        $this->assertTrue($w2_time >= max($w1_1_time, $w1_2_time) + 25 + 5);

        $l2_time = $toMins($l2->scheduled_time);
        $this->assertTrue($l2_time >= max($l1_time, $w2_time) + 25 + 5);

        $gf_time = $toMins($gf->scheduled_time);
        $this->assertTrue($gf_time >= max($l2_time, $w2_time) + 25 + 5);
    }

    public function test_score_recording_validates_max_two_digits()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $tournament = Tournament::create([
            'name' => 'Validation Cup',
            'type' => 'single_elimination',
            'min_players' => 4,
            'max_players' => 4,
            'start_time' => '08:00',
            'match_duration' => 25,
            'rest_time' => 5,
        ]);

        $tp1 = TournamentPlayer::create(['tournament_id' => $tournament->id, 'player1_name' => 'Team A', 'player2_name' => 'Player A2', 'seed' => 1]);
        $tp2 = TournamentPlayer::create(['tournament_id' => $tournament->id, 'player1_name' => 'Team B', 'player2_name' => 'Player B2', 'seed' => 2]);

        $match = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 1,
            'match_order' => 1,
            'bracket' => 'winners',
            'team1_id' => $tp1->id,
            'team2_id' => $tp2->id,
        ]);

        // 1. Valid scores (2 digits or less) should pass
        $response = $this->post(route('tournaments.record-score', $match->id), [
            'team1_score' => 11,
            'team2_score' => 9,
        ]);
        $response->assertRedirect();
        $match->refresh();
        $this->assertEquals(11, $match->team1_score);
        $this->assertEquals(9, $match->team2_score);

        // Reset match score
        $match->update(['team1_score' => null, 'team2_score' => null, 'winner_id' => null]);

        // 2. Scores higher than 99 (3 digits) should fail validation
        $response = $this->post(route('tournaments.record-score', $match->id), [
            'team1_score' => 100,
            'team2_score' => 9,
        ]);
        $response->assertSessionHasErrors(['team1_score']);

        $response = $this->post(route('tournaments.record-score', $match->id), [
            'team1_score' => 11,
            'team2_score' => 101,
        ]);
        $response->assertSessionHasErrors(['team2_score']);
    }

    public function test_match_bypass_frees_court_and_prioritizes_requeue()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $day = \App\Models\TournamentDay::create(['name' => 'Day 1', 'date' => '2026-06-06']);
        $sub = \App\Models\TournamentSubFolder::create([
            'name' => 'Sub A',
            'tournament_day_id' => $day->id,
            'assigned_courts' => [1],
        ]);

        $tournament = Tournament::create([
            'name' => 'Bypass Cup',
            'type' => 'single_elimination',
            'min_players' => 4,
            'max_players' => 4,
            'start_time' => '08:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'tournament_day_id' => $day->id,
            'tournament_sub_folder_id' => $sub->id,
            'status' => 'in_progress',
        ]);

        $tp1 = TournamentPlayer::create(['tournament_id' => $tournament->id, 'player1_name' => 'Team A', 'player2_name' => 'Player A2', 'seed' => 1]);
        $tp2 = TournamentPlayer::create(['tournament_id' => $tournament->id, 'player1_name' => 'Team B', 'player2_name' => 'Player B2', 'seed' => 2]);
        $tp3 = TournamentPlayer::create(['tournament_id' => $tournament->id, 'player1_name' => 'Team C', 'player2_name' => 'Player C2', 'seed' => 3]);
        $tp4 = TournamentPlayer::create(['tournament_id' => $tournament->id, 'player1_name' => 'Team D', 'player2_name' => 'Player D2', 'seed' => 4]);

        $match1 = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 1,
            'match_order' => 1,
            'bracket' => 'winners',
            'team1_id' => $tp1->id,
            'team2_id' => $tp2->id,
            'scheduled_time' => '08:00',
            'court_number' => 1,
        ]);

        $match2 = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 1,
            'match_order' => 2,
            'bracket' => 'winners',
            'team1_id' => $tp3->id,
            'team2_id' => $tp4->id,
            'scheduled_time' => '08:25',
        ]);

        // 1. Bypass Match 1. Court 1 should be freed and immediately assigned to Match 2.
        $response = $this->post(route('tournaments.bypass-match', $match1->id));
        $response->assertRedirect();

        $match1->refresh();
        $match2->refresh();

        $this->assertEquals(1, $match1->bypass_count);
        $this->assertNull($match1->court_number);
        $this->assertEquals(1, (int) $match2->court_number);

        // 2. Score Match 2 to complete it. Match 1 (which has bypass_count == 1) should be prioritized and get the court back immediately.
        $this->post(route('tournaments.record-score', $match2->id), [
            'team1_score' => 11,
            'team2_score' => 5,
        ]);

        $match1->refresh();
        $this->assertEquals(1, (int) $match1->court_number);
    }

    public function test_match_forfeit_assigns_correct_score_and_advances_winner()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $tournament = Tournament::create([
            'name' => 'Forfeit Cup',
            'type' => 'single_elimination',
            'min_players' => 4,
            'max_players' => 4,
            'start_time' => '08:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'status' => 'in_progress',
        ]);

        $tp1 = TournamentPlayer::create(['tournament_id' => $tournament->id, 'player1_name' => 'Team A', 'player2_name' => 'Player A2', 'seed' => 1]);
        $tp2 = TournamentPlayer::create(['tournament_id' => $tournament->id, 'player1_name' => 'Team B', 'player2_name' => 'Player B2', 'seed' => 2]);

        $nextMatch = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 2,
            'match_order' => 1,
            'bracket' => 'winners',
        ]);

        $match = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 1,
            'match_order' => 1,
            'bracket' => 'winners',
            'team1_id' => $tp1->id,
            'team2_id' => $tp2->id,
            'next_match_id' => $nextMatch->id,
            'next_match_slot' => 'team1',
        ]);

        // Forfeit the match making team2 (tp2) the winner with score 15
        $response = $this->post(route('tournaments.forfeit-match', $match->id), [
            'winner_id' => $tp2->id,
            'winning_score' => 15,
        ]);

        $response->assertRedirect();
        $match->refresh();
        $nextMatch->refresh();

        $this->assertTrue($match->is_forfeited);
        $this->assertEquals($tp2->id, $match->winner_id);
        $this->assertEquals(0, $match->team1_score);
        $this->assertEquals(15, $match->team2_score);
        $this->assertEquals($tp2->id, $nextMatch->team1_id);

        // Undo/Reset the match and verify forfeit state is removed
        $response = $this->post(route('tournaments.reset-match', $match->id));
        $response->assertRedirect();
        $match->refresh();

        $this->assertFalse($match->is_forfeited);
        $this->assertEquals(0, $match->bypass_count);
        $this->assertNull($match->winner_id);
        $this->assertNull($match->team1_score);
        $this->assertNull($match->team2_score);
    }
}


