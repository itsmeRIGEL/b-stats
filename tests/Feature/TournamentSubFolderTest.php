<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tournament;
use App\Models\TournamentDay;
use App\Models\TournamentSubFolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentSubFolderTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    private function makeDay(array $overrides = []): TournamentDay
    {
        return TournamentDay::create(array_merge([
            'name' => 'Test Day',
            'date' => '2026-06-06',
        ], $overrides));
    }

    private function makeTournament(array $overrides = []): Tournament
    {
        return Tournament::create(array_merge([
            'name' => 'Test Tournament',
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

    public function test_can_create_sub_folder()
    {
        $this->actingAdmin();
        $day = $this->makeDay();

        $response = $this->post(route('tournament-sub-folders.store'), [
            'name' => 'Morning brackets',
            'tournament_day_id' => $day->id,
        ]);

        $response->assertRedirect();
        $this->assertEquals('Sub-folder created.', session('success'));
        $this->assertDatabaseHas('tournament_sub_folders', [
            'name' => 'Morning brackets',
            'tournament_day_id' => $day->id,
        ]);
    }

    public function test_sub_folder_validates_required_fields()
    {
        $this->actingAdmin();

        $response = $this->post(route('tournament-sub-folders.store'), []);

        $response->assertSessionHasErrors(['name', 'tournament_day_id']);
    }

    public function test_sub_folder_validates_day_exists()
    {
        $this->actingAdmin();

        $response = $this->post(route('tournament-sub-folders.store'), [
            'name' => 'Orphan',
            'tournament_day_id' => 99999,
        ]);

        $response->assertSessionHasErrors(['tournament_day_id']);
    }

    public function test_can_update_sub_folder()
    {
        $this->actingAdmin();
        $day = $this->makeDay();
        $sub = TournamentSubFolder::create(['name' => 'Old', 'tournament_day_id' => $day->id, 'order' => 0]);

        $response = $this->put(route('tournament-sub-folders.update', $sub->id), [
            'name' => 'Renamed',
            'order' => 5,
        ]);

        $response->assertRedirect();
        $sub->refresh();
        $this->assertEquals('Renamed', $sub->name);
        $this->assertEquals(5, (int) $sub->order);
    }

    public function test_deleting_day_cascades_to_sub_folders()
    {
        $this->actingAdmin();
        $day = $this->makeDay();
        $sub = TournamentSubFolder::create(['name' => 'Will be cascaded', 'tournament_day_id' => $day->id]);

        $day->delete();

        $this->assertDatabaseMissing('tournament_sub_folders', ['id' => $sub->id]);
    }

    public function test_deleting_sub_folder_nullifies_member_sub_folder_id()
    {
        $this->actingAdmin();
        $day = $this->makeDay();
        $sub = TournamentSubFolder::create(['name' => 'Sub', 'tournament_day_id' => $day->id]);
        $t1 = $this->makeTournament(['tournament_day_id' => $day->id, 'tournament_sub_folder_id' => $sub->id]);
        $t2 = $this->makeTournament(['tournament_day_id' => $day->id, 'tournament_sub_folder_id' => $sub->id]);

        $response = $this->delete(route('tournament-sub-folders.destroy', $sub->id));

        $response->assertRedirect();
        $t1->refresh();
        $t2->refresh();
        $this->assertNull($t1->tournament_sub_folder_id);
        $this->assertNull($t2->tournament_sub_folder_id);
        $this->assertEquals($day->id, $t1->tournament_day_id);
    }

    public function test_bulk_assign_moves_tournaments_to_sub_folder()
    {
        $this->actingAdmin();
        $day = $this->makeDay();
        $sub = TournamentSubFolder::create(['name' => 'Sub', 'tournament_day_id' => $day->id]);
        $t1 = $this->makeTournament(['tournament_day_id' => $day->id]);
        $t2 = $this->makeTournament(['tournament_day_id' => $day->id]);

        $response = $this->post(route('tournaments.bulk-assign-sub-folder'), [
            'tournament_ids' => [$t1->id, $t2->id],
            'tournament_sub_folder_id' => $sub->id,
        ]);

        $response->assertRedirect();
        $t1->refresh();
        $t2->refresh();
        $this->assertEquals($sub->id, $t1->tournament_sub_folder_id);
        $this->assertEquals($sub->id, $t2->tournament_sub_folder_id);
    }

    public function test_bulk_assign_with_null_sub_folder_unassigns()
    {
        $this->actingAdmin();
        $day = $this->makeDay();
        $sub = TournamentSubFolder::create(['name' => 'Sub', 'tournament_day_id' => $day->id]);
        $t = $this->makeTournament(['tournament_day_id' => $day->id, 'tournament_sub_folder_id' => $sub->id]);

        $response = $this->post(route('tournaments.bulk-assign-sub-folder'), [
            'tournament_ids' => [$t->id],
            'tournament_sub_folder_id' => null,
        ]);

        $response->assertRedirect();
        $t->refresh();
        $this->assertNull($t->tournament_sub_folder_id);
    }

    public function test_bulk_assign_validates_tournament_ids()
    {
        $this->actingAdmin();

        $response = $this->post(route('tournaments.bulk-assign-sub-folder'), [
            'tournament_ids' => [99999],
            'tournament_sub_folder_id' => null,
        ]);

        $response->assertSessionHasErrors(['tournament_ids.0']);
    }

    public function test_bulk_assign_validates_sub_folder_id_exists()
    {
        $this->actingAdmin();
        $t = $this->makeTournament();

        $response = $this->post(route('tournaments.bulk-assign-sub-folder'), [
            'tournament_ids' => [$t->id],
            'tournament_sub_folder_id' => 99999,
        ]);

        $response->assertSessionHasErrors(['tournament_sub_folder_id']);
    }

    public function test_bulk_assign_sub_folder_auto_syncs_tournament_day_id()
    {
        $this->actingAdmin();
        $day1 = $this->makeDay(['name' => 'Day 1']);
        $day2 = $this->makeDay(['name' => 'Day 2']);
        $sub2 = TournamentSubFolder::create(['name' => 'Day 2 Sub', 'tournament_day_id' => $day2->id]);
        $t = $this->makeTournament(['tournament_day_id' => $day1->id]);

        $response = $this->post(route('tournaments.bulk-assign-sub-folder'), [
            'tournament_ids' => [$t->id],
            'tournament_sub_folder_id' => $sub2->id,
        ]);

        $response->assertRedirect();
        $t->refresh();
        $this->assertEquals($sub2->id, $t->tournament_sub_folder_id);
        $this->assertEquals($day2->id, $t->tournament_day_id);
    }

    public function test_tournament_index_returns_sub_folders()
    {
        $this->actingAdmin();
        $day = $this->makeDay();
        TournamentSubFolder::create(['name' => 'Sub A', 'tournament_day_id' => $day->id, 'order' => 1]);
        TournamentSubFolder::create(['name' => 'Sub B', 'tournament_day_id' => $day->id, 'order' => 2]);

        $response = $this->get(route('tournaments.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tournament')
            ->has('tournamentSubFolders', 2)
            ->where('tournamentSubFolders.0.name', 'Sub A')
            ->where('tournamentSubFolders.0.tournaments_count', 0)
        );
    }

    public function test_can_create_tournament_with_sub_folder_id()
    {
        $this->actingAdmin();
        $day = $this->makeDay();
        $sub = TournamentSubFolder::create(['name' => 'Sub', 'tournament_day_id' => $day->id]);

        $response = $this->post(route('tournaments.store'), [
            'name' => 'Sub-bracketed',
            'type' => 'single_elimination',
            'category' => 'mens',
            'min_players' => 4,
            'max_players' => 8,
            'best_of' => 1,
            'start_time' => '08:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => false,
            'tournament_day_id' => $day->id,
            'tournament_sub_folder_id' => $sub->id,
        ]);

        $response->assertRedirect();
        $t = Tournament::where('name', 'Sub-bracketed')->first();
        $this->assertNotNull($t);
        $this->assertEquals($sub->id, $t->tournament_sub_folder_id);
        $this->assertEquals($day->id, $t->tournament_day_id);
    }

    public function test_can_update_tournament_sub_folder_id_via_bracket_settings()
    {
        $this->actingAdmin();
        $day = $this->makeDay();
        $subA = TournamentSubFolder::create(['name' => 'Sub A', 'tournament_day_id' => $day->id]);
        $subB = TournamentSubFolder::create(['name' => 'Sub B', 'tournament_day_id' => $day->id]);
        $t = $this->makeTournament(['tournament_day_id' => $day->id, 'tournament_sub_folder_id' => $subA->id]);

        $response = $this->put(route('tournaments.update', $t->id), [
            'tournament_sub_folder_id' => $subB->id,
        ]);

        $response->assertRedirect();
        $t->refresh();
        $this->assertEquals($subB->id, $t->tournament_sub_folder_id);
    }

    public function test_can_update_assigned_courts_and_assigns_them_dynamically()
    {
        $this->actingAdmin();
        $day = $this->makeDay();
        $sub = TournamentSubFolder::create([
            'name' => 'Sub A',
            'tournament_day_id' => $day->id,
            'start_time' => '08:00',
            'match_duration' => 15,
            'rest_time' => 0,
            'enable_break' => false,
        ]);

        // Create 2 tournaments in progress in this folder
        $t1 = $this->makeTournament([
            'status' => 'in_progress',
            'tournament_day_id' => $day->id,
            'tournament_sub_folder_id' => $sub->id,
        ]);
        $t2 = $this->makeTournament([
            'status' => 'in_progress',
            'tournament_day_id' => $day->id,
            'tournament_sub_folder_id' => $sub->id,
        ]);

        // Create players
        $p1 = \App\Models\TournamentPlayer::create(['tournament_id' => $t1->id, 'player1_name' => 'A', 'player2_name' => 'B']);
        $p2 = \App\Models\TournamentPlayer::create(['tournament_id' => $t1->id, 'player1_name' => 'C', 'player2_name' => 'D']);
        $p3 = \App\Models\TournamentPlayer::create(['tournament_id' => $t2->id, 'player1_name' => 'E', 'player2_name' => 'F']);
        $p4 = \App\Models\TournamentPlayer::create(['tournament_id' => $t2->id, 'player1_name' => 'G', 'player2_name' => 'H']);

        // Create some matches that are ready (team1_id and team2_id are set)
        $m1 = \App\Models\TournamentMatch::create([
            'tournament_id' => $t1->id,
            'round' => 1,
            'match_order' => 1,
            'bracket' => 'winners',
            'team1_id' => $p1->id,
            'team2_id' => $p2->id,
            'scheduled_time' => '08:00',
        ]);
        $m2 = \App\Models\TournamentMatch::create([
            'tournament_id' => $t2->id,
            'round' => 1,
            'match_order' => 1,
            'bracket' => 'winners',
            'team1_id' => $p3->id,
            'team2_id' => $p4->id,
            'scheduled_time' => '08:15',
        ]);

        // Update subfolder to assign Court 1 and Court 2
        $response = $this->put(route('tournament-sub-folders.update', $sub->id), [
            'assigned_courts' => [1, 2],
        ]);

        $response->assertRedirect();
        $sub->refresh();
        $this->assertEquals([1, 2], $sub->assigned_courts);

        // Verify matches got assigned courts
        $m1->refresh();
        $m2->refresh();
        $this->assertEquals(1, (int) $m1->court_number);
        $this->assertEquals(2, (int) $m2->court_number);
    }
}
