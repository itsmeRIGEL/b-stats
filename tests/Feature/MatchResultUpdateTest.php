<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Player;
use App\Models\GameMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchResultUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_previous_match_result()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $p1 = Player::create(['name' => 'Player 1']);
        $p2 = Player::create(['name' => 'Player 2']);
        $p3 = Player::create(['name' => 'Player 3']);
        $p4 = Player::create(['name' => 'Player 4']);

        $match = GameMatch::create([
            'player_1_id' => $p1->id,
            'player_2_id' => $p2->id,
            'player_3_id' => $p3->id,
            'player_4_id' => $p4->id,
            'player_1_score' => 6,
            'player_2_score' => 7,
            'match_date' => '2026-06-05',
            'is_walkin' => false,
            'fee_amount' => 0.00,
        ]);

        $response = $this->put(route('matches.update', $match), [
            'player_1_id' => $p1->id,
            'player_2_id' => $p2->id,
            'player_3_id' => $p3->id,
            'player_4_id' => $p4->id,
            'player_1_score' => 11,
            'player_2_score' => 9,
            'match_date' => '2026-06-05',
            'is_walkin' => true,
        ]);

        $response->assertRedirect();
        
        $match->refresh();
        $this->assertEquals(11, $match->player_1_score);
        $this->assertEquals(9, $match->player_2_score);
        $this->assertTrue((bool)$match->is_walkin);
    }

    public function test_cannot_save_or_update_draw_scores()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $p1 = Player::create(['name' => 'Player 1']);
        $p2 = Player::create(['name' => 'Player 2']);

        // Test store draw
        $response = $this->post(route('matches.store'), [
            'player_1_id' => $p1->id,
            'player_2_id' => $p2->id,
            'player_1_score' => 10,
            'player_2_score' => 10,
            'match_date' => '2026-06-05',
        ]);
        $response->assertSessionHasErrors(['player_2_score']);

        // Test update draw
        $match = GameMatch::create([
            'player_1_id' => $p1->id,
            'player_2_id' => $p2->id,
            'player_1_score' => 11,
            'player_2_score' => 9,
            'match_date' => '2026-06-05',
            'fee_amount' => 0.00,
        ]);

        $response = $this->put(route('matches.update', $match), [
            'player_1_id' => $p1->id,
            'player_2_id' => $p2->id,
            'player_1_score' => 8,
            'player_2_score' => 8,
            'match_date' => '2026-06-05',
        ]);
        $response->assertSessionHasErrors(['player_2_score']);
    }
}
