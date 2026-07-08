<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllTimeStatsAccessTest extends TestCase
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

    public function test_admin_can_open_all_time_stats_page()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('all-time-stats'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('AllTimeStats')
            ->has('players')
            ->has('matches')
            ->where('settings.scoring_win_points', 10)
        );
    }

    public function test_scheduler_all_time_stats_only_include_players_from_owned_venue()
    {
        $scheduler = $this->createSchedulerWithVenue('scheduler@example.com');
        $otherScheduler = $this->createSchedulerWithVenue('other-scheduler@example.com');

        $ownedWinnerUser = User::factory()->create([
            'username' => 'own-winner',
            'first_name' => 'Owned',
            'last_name' => 'Winner',
            'all_time_stats_visible_fields' => ['username'],
        ]);
        $ownedLoserUser = User::factory()->create(['username' => 'own-loser']);
        $foreignWinnerUser = User::factory()->create(['username' => 'foreign-winner']);
        $foreignLoserUser = User::factory()->create(['username' => 'foreign-loser']);

        $ownedWinner = Player::create([
            'user_id' => $ownedWinnerUser->id,
            'name' => 'Owned Winner',
            'show_in_roster' => true,
            'venue_id' => $scheduler->venue_id,
        ]);
        $ownedLoser = Player::create([
            'user_id' => $ownedLoserUser->id,
            'name' => 'Owned Loser',
            'show_in_roster' => true,
            'venue_id' => $scheduler->venue_id,
        ]);
        $foreignWinner = Player::create([
            'user_id' => $foreignWinnerUser->id,
            'name' => 'Foreign Winner',
            'show_in_roster' => true,
            'venue_id' => $otherScheduler->venue_id,
        ]);
        $foreignLoser = Player::create([
            'user_id' => $foreignLoserUser->id,
            'name' => 'Foreign Loser',
            'show_in_roster' => true,
            'venue_id' => $otherScheduler->venue_id,
        ]);

        GameMatch::create([
            'player_1_id' => $ownedWinner->id,
            'player_2_id' => $ownedLoser->id,
            'player_1_score' => 11,
            'player_2_score' => 7,
            'match_date' => now()->toDateString(),
            'loss_points' => 5,
            'is_tallied' => true,
            'is_walkin' => true,
            'venue_id' => $scheduler->venue_id,
        ]);

        GameMatch::create([
            'player_1_id' => $foreignWinner->id,
            'player_2_id' => $foreignLoser->id,
            'player_1_score' => 11,
            'player_2_score' => 3,
            'match_date' => now()->toDateString(),
            'loss_points' => 5,
            'is_tallied' => true,
            'is_walkin' => true,
            'venue_id' => $otherScheduler->venue_id,
        ]);

        $this->actingAs($scheduler);

        $response = $this->get(route('all-time-stats'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('AllTimeStats')
            ->has('players', 2)
            ->where('players.0.name', 'own-winner')
            ->where('players.0.available_sections.0', 'stats')
            ->where('players.0.available_sections.1', 'profile')
            ->where('players.0.available_sections.2', 'membership')
            ->where('players.0.profile_details.username', 'own-winner')
            ->where('players.0.profile_details.first_name', null)
            ->where('players.1.name', 'own-loser')
            ->where('venueLabel', $scheduler->currentVenue()?->name)
        );
    }
}
