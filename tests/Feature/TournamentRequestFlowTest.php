<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentDay;
use App\Models\TournamentRequest;
use App\Models\TournamentSubFolder;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_view_venues_and_submit_tournament_request(): void
    {
        $player = User::factory()->create([
            'role' => 'player',
            'email_verified_at' => now(),
        ]);

        $scheduler = User::factory()->create([
            'role' => 'scheduler',
            'email_verified_at' => now(),
        ]);

        $venue = Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Rally Hall',
            'address' => 'Court Street',
            'court_count' => 4,
            'is_active' => true,
        ]);

        $this->actingAs($player);

        $this->get(route('venues.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PlayerVenues')
                ->has('venues', 1)
            );

        $this->post(route('tournament-requests.store'), [
            'venue_id' => $venue->id,
            'name' => 'Community Open',
            'preferred_date' => now()->addWeek()->toDateString(),
            'preferred_start_time' => '09:00',
            'notes' => 'Weekend event request',
        ])->assertRedirect();

        $this->assertDatabaseHas('tournament_requests', [
            'user_id' => $player->id,
            'venue_id' => $venue->id,
            'name' => 'Community Open',
            'category' => 'mens',
            'status' => 'pending',
        ]);
    }

    public function test_scheduler_can_approve_tournament_request_and_create_main_folder_only(): void
    {
        $scheduler = User::factory()->create([
            'role' => 'scheduler',
            'email_verified_at' => now(),
        ]);

        $venue = Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Arena One',
            'court_count' => 3,
            'is_active' => true,
        ]);

        $scheduler->update(['venue_id' => $venue->id]);

        $player = User::factory()->create([
            'role' => 'player',
            'email_verified_at' => now(),
        ]);

        $requestModel = TournamentRequest::create([
            'user_id' => $player->id,
            'venue_id' => $venue->id,
            'name' => 'Summer Smash',
            'category' => 'mens',
            'preferred_start_time' => '10:00',
            'status' => 'pending',
        ]);

        $this->actingAs($scheduler)
            ->post(route('tournament-requests.approve', $requestModel))
            ->assertRedirect();

        $requestModel->refresh();

        $this->assertSame('approved', $requestModel->status);
        $this->assertNull($requestModel->tournament_id);
        $this->assertNotNull($requestModel->tournament_day_id);
        $this->assertDatabaseHas('tournament_days', [
            'id' => $requestModel->tournament_day_id,
            'name' => 'Summer Smash',
            'venue_id' => $venue->id,
            'status' => 'active',
        ]);

        $day = TournamentDay::findOrFail($requestModel->tournament_day_id);
        $this->assertEquals($venue->id, $day->venue_id);
    }

    public function test_approved_player_can_create_and_manage_owned_tournament_inside_approved_main_folder(): void
    {
        $scheduler = User::factory()->create([
            'role' => 'scheduler',
            'email_verified_at' => now(),
        ]);

        $venue = Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Center Court',
            'court_count' => 4,
            'is_active' => true,
        ]);

        $player = User::factory()->create([
            'role' => 'player',
            'email_verified_at' => now(),
        ]);

        $requestModel = TournamentRequest::create([
            'user_id' => $player->id,
            'venue_id' => $venue->id,
            'name' => 'Player Managed Open',
            'category' => 'mix',
            'preferred_start_time' => '09:00',
            'status' => 'pending',
        ]);

        $this->actingAs($scheduler)
            ->post(route('tournament-requests.approve', $requestModel))
            ->assertRedirect();

        $requestModel->refresh();
        $day = TournamentDay::findOrFail($requestModel->tournament_day_id);
        $day->update(['assigned_courts' => [1, 2]]);

        $this->actingAs($player)
            ->get(route('tournaments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tournament')
                ->has('tournamentDays', 1)
                ->has('tournaments', 0)
            );

        $this->actingAs($player)
            ->post(route('tournaments.store'), [
                'name' => 'Player Managed Open',
                'type' => 'single_elimination',
                'category' => 'mix',
                'min_players' => 2,
                'max_players' => 8,
                'best_of' => 1,
                'start_time' => '09:00',
                'match_duration' => 25,
                'rest_time' => 5,
                'enable_break' => false,
                'tournament_day_id' => $day->id,
                'assigned_courts' => [],
            ])
            ->assertRedirect();

        $tournament = Tournament::where('manager_user_id', $player->id)->firstOrFail();
        $this->assertSame([1, 2], $tournament->assigned_courts);

        $this->actingAs($player)
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tournament')
                ->where('activeTournament.id', $tournament->id)
            );

        $this->actingAs($player)
            ->post(route('tournaments.add-team', $tournament), [
                'player1_name' => 'Alice',
                'player2_name' => 'Bob',
            ])
            ->assertRedirect(route('tournaments.show', $tournament));

        $this->assertDatabaseHas('tournament_players', [
            'tournament_id' => $tournament->id,
            'player1_name' => 'Alice',
            'player2_name' => 'Bob',
        ]);
    }

    public function test_finished_player_day_locks_tournament_until_edit_access_is_reapproved(): void
    {
        $scheduler = User::factory()->create([
            'role' => 'scheduler',
            'email_verified_at' => now(),
        ]);

        $venue = Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Lock Court',
            'court_count' => 2,
            'is_active' => true,
        ]);

        $scheduler->update(['venue_id' => $venue->id]);

        $player = User::factory()->create([
            'role' => 'player',
            'email_verified_at' => now(),
        ]);

        $day = TournamentDay::create([
            'name' => 'Locked Workspace',
            'date' => now()->toDateString(),
            'status' => 'active',
            'venue_id' => $venue->id,
        ]);

        $tournament = Tournament::create([
            'name' => 'Locked Workspace',
            'type' => 'single_elimination',
            'category' => 'mix',
            'status' => 'setup',
            'min_players' => 2,
            'max_players' => 8,
            'best_of' => 1,
            'start_time' => '09:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => false,
            'tournament_day_id' => $day->id,
            'venue_id' => $venue->id,
            'manager_user_id' => $player->id,
        ]);

        $this->actingAs($player)
            ->post(route('tournament-days.finish-player-access', $day))
            ->assertRedirect();

        $this->assertDatabaseHas('tournament_days', [
            'id' => $day->id,
            'status' => 'finished',
        ]);

        $this->actingAs($player)
            ->post(route('tournaments.add-team', $tournament), [
                'player1_name' => 'Alice',
                'player2_name' => 'Bob',
            ])
            ->assertForbidden();

        $this->actingAs($player)
            ->post(route('tournament-requests.store'), [
                'venue_id' => $venue->id,
                'tournament_id' => $tournament->id,
                'request_type' => 'edit_access',
                'name' => $tournament->name,
                'category' => $tournament->category,
                'notes' => 'Need to reopen for corrections',
            ])
            ->assertRedirect();

        $editRequest = TournamentRequest::latest()->first();
        $this->assertSame('edit_access', $editRequest->request_type);
        $this->assertSame($tournament->id, $editRequest->tournament_id);

        $this->actingAs($scheduler)
            ->post(route('tournament-requests.approve', $editRequest))
            ->assertRedirect();

        $this->assertDatabaseHas('tournament_days', [
            'id' => $day->id,
            'status' => 'active',
        ]);

        $this->actingAs($player)
            ->post(route('tournaments.add-team', $tournament), [
                'player1_name' => 'Alice',
                'player2_name' => 'Bob',
            ])
            ->assertRedirect(route('tournaments.show', $tournament));
    }

    public function test_approved_player_can_manage_their_day_toolbar_actions(): void
    {
        $scheduler = User::factory()->create([
            'role' => 'scheduler',
            'email_verified_at' => now(),
        ]);

        $venue = Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Toolbar Court',
            'court_count' => 3,
            'is_active' => true,
        ]);

        $player = User::factory()->create([
            'role' => 'player',
            'email_verified_at' => now(),
        ]);

        $requestModel = TournamentRequest::create([
            'user_id' => $player->id,
            'venue_id' => $venue->id,
            'name' => 'Toolbar Day',
            'category' => 'mens',
            'status' => 'pending',
        ]);

        $this->actingAs($scheduler)
            ->post(route('tournament-requests.approve', $requestModel))
            ->assertRedirect();

        $requestModel->refresh();
        $day = TournamentDay::findOrFail($requestModel->tournament_day_id);

        $this->actingAs($player)
            ->put(route('tournament-days.update', $day), [
                'name' => 'Toolbar Day Updated',
                'date' => $day->date->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tournament_days', [
            'id' => $day->id,
            'name' => 'Toolbar Day Updated',
        ]);

        $this->actingAs($player)
            ->post(route('tournament-sub-folders.store'), [
                'name' => 'Player Sub Folder',
                'tournament_day_id' => $day->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tournament_sub_folders', [
            'name' => 'Player Sub Folder',
            'tournament_day_id' => $day->id,
            'venue_id' => $venue->id,
        ]);

        $subFolder = TournamentSubFolder::where('tournament_day_id', $day->id)->first();
        $this->assertNotNull($subFolder);
        $this->assertNull($subFolder->assigned_courts);

        $this->actingAs($player)
            ->delete(route('tournament-days.destroy', $day))
            ->assertRedirect();

        $this->assertDatabaseMissing('tournament_days', [
            'id' => $day->id,
        ]);
        $this->assertDatabaseMissing('tournament_sub_folders', [
            'id' => $subFolder->id,
        ]);
    }

    public function test_approved_player_can_see_empty_subfolders_inside_their_approved_main_folder(): void
    {
        $scheduler = User::factory()->create([
            'role' => 'scheduler',
            'email_verified_at' => now(),
        ]);

        $venue = Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Visibility Court',
            'court_count' => 3,
            'is_active' => true,
        ]);

        $player = User::factory()->create([
            'role' => 'player',
            'email_verified_at' => now(),
        ]);

        $requestModel = TournamentRequest::create([
            'user_id' => $player->id,
            'venue_id' => $venue->id,
            'name' => 'Visible Workspace',
            'category' => 'mens',
            'status' => 'pending',
        ]);

        $this->actingAs($scheduler)
            ->post(route('tournament-requests.approve', $requestModel))
            ->assertRedirect();

        $requestModel->refresh();
        $day = TournamentDay::findOrFail($requestModel->tournament_day_id);

        $subFolder = TournamentSubFolder::create([
            'name' => 'Morning Brackets',
            'tournament_day_id' => $day->id,
            'venue_id' => $venue->id,
            'order' => 1,
        ]);

        $this->actingAs($player)
            ->get(route('tournaments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tournament')
                ->has('tournamentDays', 1)
                ->has('tournamentSubFolders', 1)
                ->where('tournamentSubFolders.0.id', $subFolder->id)
                ->where('tournamentSubFolders.0.tournament_day_id', $day->id)
            );
    }

    public function test_approved_player_can_move_owned_tournament_into_owned_subfolder(): void
    {
        $scheduler = User::factory()->create([
            'role' => 'scheduler',
            'email_verified_at' => now(),
        ]);

        $venue = Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Assignment Court',
            'court_count' => 3,
            'is_active' => true,
        ]);

        $player = User::factory()->create([
            'role' => 'player',
            'email_verified_at' => now(),
        ]);

        $requestModel = TournamentRequest::create([
            'user_id' => $player->id,
            'venue_id' => $venue->id,
            'name' => 'Player Folder Access',
            'category' => 'mens',
            'status' => 'pending',
        ]);

        $this->actingAs($scheduler)
            ->post(route('tournament-requests.approve', $requestModel))
            ->assertRedirect();

        $requestModel->refresh();
        $day = TournamentDay::findOrFail($requestModel->tournament_day_id);

        $subFolder = TournamentSubFolder::create([
            'name' => 'Mens',
            'tournament_day_id' => $day->id,
            'venue_id' => $venue->id,
            'order' => 1,
        ]);

        $tournament = Tournament::create([
            'name' => 'Bracket 1',
            'type' => 'round_robin',
            'category' => 'mens',
            'status' => 'setup',
            'min_players' => 2,
            'max_players' => 4,
            'best_of' => 1,
            'start_time' => '08:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => false,
            'tournament_day_id' => $day->id,
            'venue_id' => $venue->id,
            'manager_user_id' => $player->id,
        ]);

        $this->actingAs($player)
            ->post(route('tournaments.bulk-assign-sub-folder'), [
                'tournament_ids' => [$tournament->id],
                'tournament_sub_folder_id' => $subFolder->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tournaments', [
            'id' => $tournament->id,
            'tournament_day_id' => $day->id,
            'tournament_sub_folder_id' => $subFolder->id,
            'manager_user_id' => $player->id,
            'venue_id' => $venue->id,
        ]);
    }

    public function test_approved_player_can_delete_owned_tournament_cards(): void
    {
        $scheduler = User::factory()->create([
            'role' => 'scheduler',
            'email_verified_at' => now(),
        ]);

        $venue = Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Delete Court',
            'court_count' => 3,
            'is_active' => true,
        ]);

        $player = User::factory()->create([
            'role' => 'player',
            'email_verified_at' => now(),
        ]);

        $requestModel = TournamentRequest::create([
            'user_id' => $player->id,
            'venue_id' => $venue->id,
            'name' => 'Delete Workspace',
            'category' => 'mens',
            'status' => 'pending',
        ]);

        $this->actingAs($scheduler)
            ->post(route('tournament-requests.approve', $requestModel))
            ->assertRedirect();

        $requestModel->refresh();
        $day = TournamentDay::findOrFail($requestModel->tournament_day_id);

        $single = Tournament::create([
            'name' => 'Single Delete',
            'type' => 'round_robin',
            'category' => 'mens',
            'status' => 'setup',
            'min_players' => 2,
            'max_players' => 4,
            'best_of' => 1,
            'start_time' => '08:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => false,
            'tournament_day_id' => $day->id,
            'venue_id' => $venue->id,
            'manager_user_id' => $player->id,
        ]);

        $bulkA = Tournament::create([
            'name' => 'Bulk Delete A',
            'type' => 'round_robin',
            'category' => 'mens',
            'status' => 'setup',
            'min_players' => 2,
            'max_players' => 4,
            'best_of' => 1,
            'start_time' => '08:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => false,
            'tournament_day_id' => $day->id,
            'venue_id' => $venue->id,
            'manager_user_id' => $player->id,
        ]);

        $bulkB = Tournament::create([
            'name' => 'Bulk Delete B',
            'type' => 'round_robin',
            'category' => 'mens',
            'status' => 'setup',
            'min_players' => 2,
            'max_players' => 4,
            'best_of' => 1,
            'start_time' => '08:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => false,
            'tournament_day_id' => $day->id,
            'venue_id' => $venue->id,
            'manager_user_id' => $player->id,
        ]);

        $this->actingAs($player)
            ->delete(route('tournaments.destroy', $single))
            ->assertRedirect(route('tournaments.index'));

        $this->assertDatabaseMissing('tournaments', [
            'id' => $single->id,
        ]);

        $this->actingAs($player)
            ->post(route('tournaments.bulk-destroy'), [
                'tournament_ids' => [$bulkA->id, $bulkB->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('tournaments', [
            'id' => $bulkA->id,
        ]);
        $this->assertDatabaseMissing('tournaments', [
            'id' => $bulkB->id,
        ]);
    }

    public function test_player_subfolder_must_choose_from_scheduler_approved_day_courts(): void
    {
        $scheduler = User::factory()->create([
            'role' => 'scheduler',
            'email_verified_at' => now(),
        ]);

        $venue = Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Court Filter Venue',
            'court_count' => 4,
            'is_active' => true,
        ]);

        $player = User::factory()->create([
            'role' => 'player',
            'email_verified_at' => now(),
        ]);

        $requestModel = TournamentRequest::create([
            'user_id' => $player->id,
            'venue_id' => $venue->id,
            'name' => 'Court Filter Day',
            'category' => 'mens',
            'status' => 'pending',
        ]);

        $this->actingAs($scheduler)
            ->post(route('tournament-requests.approve', $requestModel))
            ->assertRedirect();

        $requestModel->refresh();
        $day = TournamentDay::findOrFail($requestModel->tournament_day_id);
        $day->update(['assigned_courts' => [1, 3]]);

        $this->actingAs($player)
            ->post(route('tournament-sub-folders.store'), [
                'name' => 'Filtered Sub Folder',
                'tournament_day_id' => $day->id,
                'assigned_courts' => [1],
            ])
            ->assertRedirect();

        $subFolder = TournamentSubFolder::where('name', 'Filtered Sub Folder')->firstOrFail();
        $this->assertSame([1], $subFolder->assigned_courts);

        $this->actingAs($player)
            ->from(route('tournaments.index'))
            ->post(route('tournament-sub-folders.store'), [
                'name' => 'Invalid Sub Folder',
                'tournament_day_id' => $day->id,
                'assigned_courts' => [2],
            ])
            ->assertRedirect(route('tournaments.index'))
            ->assertSessionHasErrors(['assigned_courts']);
    }

    public function test_player_can_update_owned_subfolder_without_being_redirected_away(): void
    {
        $scheduler = User::factory()->create([
            'role' => 'scheduler',
            'email_verified_at' => now(),
        ]);

        $venue = Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Edit Access Venue',
            'court_count' => 3,
            'is_active' => true,
        ]);

        $player = User::factory()->create([
            'role' => 'player',
            'email_verified_at' => now(),
        ]);

        $requestModel = TournamentRequest::create([
            'user_id' => $player->id,
            'venue_id' => $venue->id,
            'name' => 'Edit Access Day',
            'category' => 'mens',
            'status' => 'pending',
        ]);

        $this->actingAs($scheduler)
            ->post(route('tournament-requests.approve', $requestModel))
            ->assertRedirect();

        $requestModel->refresh();
        $day = TournamentDay::findOrFail($requestModel->tournament_day_id);
        $day->update(['assigned_courts' => [1, 2]]);

        $subFolder = TournamentSubFolder::create([
            'name' => 'Mens',
            'tournament_day_id' => $day->id,
            'venue_id' => $venue->id,
            'assigned_courts' => [1],
        ]);

        $this->actingAs($player)
            ->from(route('tournaments.index'))
            ->put(route('tournament-sub-folders.update', $subFolder), [
                'name' => 'Updated Mens',
                'assigned_courts' => [2],
            ])
            ->assertRedirect();

        $subFolder->refresh();
        $this->assertSame('Updated Mens', $subFolder->name);
        $this->assertSame([2], $subFolder->assigned_courts);
    }

    public function test_logged_in_player_can_open_public_live_bracket_even_if_tournament_is_from_another_venue(): void
    {
        $scheduler = User::factory()->create([
            'role' => 'scheduler',
            'email_verified_at' => now(),
        ]);

        $venue = Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Spectator Venue',
            'court_count' => 3,
            'is_active' => true,
        ]);

        $player = User::factory()->create([
            'role' => 'player',
            'email_verified_at' => now(),
        ]);

        $tournament = Tournament::create([
            'name' => 'Public Live Bracket',
            'type' => 'round_robin',
            'category' => 'mens',
            'status' => 'in_progress',
            'min_players' => 2,
            'max_players' => 4,
            'best_of' => 1,
            'start_time' => '08:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => false,
            'venue_id' => $venue->id,
        ]);

        $this->actingAs($player)
            ->get(route('tournaments.live.show', $tournament))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TournamentSpectator')
                ->where('activeTournament.id', $tournament->id)
            );
    }
}
