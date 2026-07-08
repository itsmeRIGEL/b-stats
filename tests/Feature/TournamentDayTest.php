<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentDay;
use App\Models\TournamentSubFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentDayTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_tournament_day()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $response = $this->post(route('tournament-days.store'), [
            'name' => 'MT Day 1',
            'date' => '2026-06-06',
            'assigned_courts' => [1, 2],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $day = TournamentDay::first();
        $this->assertNotNull($day);
        $this->assertEquals('MT Day 1', $day->name);
        $this->assertEquals('2026-06-06', $day->date->format('Y-m-d'));
        $this->assertSame([1, 2], $day->assigned_courts);
    }

    public function test_create_day_validates_required_fields()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $response = $this->post(route('tournament-days.store'), []);
        $response->assertSessionHasErrors(['name', 'date']);
    }

    public function test_create_day_validates_date_format()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $response = $this->post(route('tournament-days.store'), [
            'name' => 'Bad Date',
            'date' => 'not-a-date',
        ]);
        $response->assertSessionHasErrors(['date']);
    }

    public function test_can_update_tournament_day()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $day = TournamentDay::create(['name' => 'Old Name', 'date' => '2026-06-06']);

        $response = $this->put(route('tournament-days.update', $day->id), [
            'name' => 'New Name',
            'date' => '2026-06-07',
            'assigned_courts' => [2, 3],
        ]);

        $response->assertRedirect();
        $day->refresh();
        $this->assertEquals('New Name', $day->name);
        $this->assertEquals('2026-06-07', $day->date->format('Y-m-d'));
        $this->assertSame([2, 3], $day->assigned_courts);
    }

    public function test_deleting_day_nullifies_member_tournament_day_id()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $day = TournamentDay::create(['name' => 'Doomed Day', 'date' => '2026-06-06']);
        $t1 = Tournament::create([
            'name' => 'Bracket A',
            'type' => 'single_elimination',
            'category' => 'mens',
            'min_players' => 4,
            'max_players' => 8,
            'start_time' => '08:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => false,
            'tournament_day_id' => $day->id,
        ]);
        $t2 = Tournament::create([
            'name' => 'Bracket B',
            'type' => 'single_elimination',
            'category' => 'mens',
            'min_players' => 4,
            'max_players' => 8,
            'start_time' => '09:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => false,
            'tournament_day_id' => $day->id,
        ]);

        $response = $this->delete(route('tournament-days.destroy', $day->id));

        $response->assertRedirect();
        $t1->refresh();
        $t2->refresh();
        $this->assertNull($t1->tournament_day_id);
        $this->assertNull($t2->tournament_day_id);
        $this->assertNull(TournamentDay::find($day->id));
    }

    public function test_bulk_assign_moves_tournaments_to_day()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $day = TournamentDay::create([
            'name' => 'Target Day',
            'date' => '2026-06-06',
            'assigned_courts' => [1, 3],
        ]);
        $t1 = Tournament::create([
            'name' => 'B1', 'type' => 'single_elimination', 'category' => 'mens',
            'min_players' => 4, 'max_players' => 8, 'start_time' => '08:00',
            'match_duration' => 25, 'rest_time' => 5, 'enable_break' => false,
        ]);
        $t2 = Tournament::create([
            'name' => 'B2', 'type' => 'single_elimination', 'category' => 'mens',
            'min_players' => 4, 'max_players' => 8, 'start_time' => '09:00',
            'match_duration' => 25, 'rest_time' => 5, 'enable_break' => false,
        ]);

        $response = $this->post(route('tournaments.bulk-assign-day'), [
            'tournament_ids' => [$t1->id, $t2->id],
            'tournament_day_id' => $day->id,
        ]);

        $response->assertRedirect();
        $t1->refresh();
        $t2->refresh();
        $this->assertEquals($day->id, $t1->tournament_day_id);
        $this->assertEquals($day->id, $t2->tournament_day_id);
        $this->assertSame([1, 3], $t1->assigned_courts);
        $this->assertSame([1, 3], $t2->assigned_courts);
    }

    public function test_bulk_assign_with_null_day_unassigns_tournaments()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $day = TournamentDay::create(['name' => 'Source Day', 'date' => '2026-06-06']);
        $t1 = Tournament::create([
            'name' => 'B1', 'type' => 'single_elimination', 'category' => 'mens',
            'min_players' => 4, 'max_players' => 8, 'start_time' => '08:00',
            'match_duration' => 25, 'rest_time' => 5, 'enable_break' => false,
            'tournament_day_id' => $day->id,
        ]);

        $response = $this->post(route('tournaments.bulk-assign-day'), [
            'tournament_ids' => [$t1->id],
            'tournament_day_id' => null,
        ]);

        $response->assertRedirect();
        $t1->refresh();
        $this->assertNull($t1->tournament_day_id);
    }

    public function test_bulk_assign_validates_tournament_ids()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $response = $this->post(route('tournaments.bulk-assign-day'), [
            'tournament_ids' => [99999],
            'tournament_day_id' => null,
        ]);
        $response->assertSessionHasErrors(['tournament_ids.0']);
    }

    public function test_bulk_assign_validates_tournament_day_id_exists()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $t = Tournament::create([
            'name' => 'B1', 'type' => 'single_elimination', 'category' => 'mens',
            'min_players' => 4, 'max_players' => 8, 'start_time' => '08:00',
            'match_duration' => 25, 'rest_time' => 5, 'enable_break' => false,
        ]);

        $response = $this->post(route('tournaments.bulk-assign-day'), [
            'tournament_ids' => [$t->id],
            'tournament_day_id' => 99999,
        ]);
        $response->assertSessionHasErrors(['tournament_day_id']);
    }

    public function test_can_create_tournament_with_tournament_day_id()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $day = TournamentDay::create([
            'name' => 'Test Day',
            'date' => '2026-06-06',
            'assigned_courts' => [1, 2],
        ]);

        $response = $this->post(route('tournaments.store'), [
            'name' => 'Day 1 Bracket',
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
        ]);

        $response->assertRedirect();
        $t = Tournament::where('name', 'Day 1 Bracket')->first();
        $this->assertNotNull($t);
        $this->assertEquals($day->id, $t->tournament_day_id);
        $this->assertSame([1, 2], $t->assigned_courts);
    }

    public function test_can_update_tournament_day_id_via_bracket_settings()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $day1 = TournamentDay::create(['name' => 'Day 1', 'date' => '2026-06-06']);
        $day2 = TournamentDay::create(['name' => 'Day 2', 'date' => '2026-06-07']);
        $t = Tournament::create([
            'name' => 'Bracket A', 'type' => 'single_elimination', 'category' => 'mens',
            'min_players' => 4, 'max_players' => 8, 'start_time' => '08:00',
            'match_duration' => 25, 'rest_time' => 5, 'enable_break' => false,
            'tournament_day_id' => $day1->id,
        ]);

        $response = $this->put(route('tournaments.update', $t->id), [
            'tournament_day_id' => $day2->id,
        ]);

        $response->assertRedirect();
        $t->refresh();
        $this->assertEquals($day2->id, $t->tournament_day_id);
    }

    public function test_tournament_index_returns_tournament_days()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $day = TournamentDay::create(['name' => 'Listed Day', 'date' => '2026-06-06']);
        Tournament::create([
            'name' => 'Bracket X', 'type' => 'single_elimination', 'category' => 'mens',
            'min_players' => 4, 'max_players' => 8, 'start_time' => '08:00',
            'match_duration' => 25, 'rest_time' => 5, 'enable_break' => false,
            'tournament_day_id' => $day->id,
        ]);

        $response = $this->get(route('tournaments.index'));
        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Tournament')
                ->has('tournamentDays', 1)
                ->where('tournamentDays.0.name', 'Listed Day')
                ->where('tournamentDays.0.tournaments_count', 1)
                ->has('tournaments', 1)
                ->where('tournaments.0.tournament_day_id', $day->id)
        );
    }

    public function test_updating_day_assigned_courts_syncs_unfiled_tournaments_and_subfolders()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $day = TournamentDay::create([
            'name' => 'Sync Day',
            'date' => '2026-06-06',
            'assigned_courts' => [1],
        ]);

        $subFolder = TournamentSubFolder::create([
            'name' => 'Morning',
            'tournament_day_id' => $day->id,
            'assigned_courts' => [1],
        ]);

        $unfiledTournament = Tournament::create([
            'name' => 'Unfiled Bracket',
            'type' => 'single_elimination',
            'category' => 'mens',
            'min_players' => 4,
            'max_players' => 8,
            'start_time' => '08:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => false,
            'tournament_day_id' => $day->id,
            'assigned_courts' => [1],
        ]);

        $filedTournament = Tournament::create([
            'name' => 'Filed Bracket',
            'type' => 'single_elimination',
            'category' => 'mens',
            'min_players' => 4,
            'max_players' => 8,
            'start_time' => '09:00',
            'match_duration' => 25,
            'rest_time' => 5,
            'enable_break' => false,
            'tournament_day_id' => $day->id,
            'tournament_sub_folder_id' => $subFolder->id,
            'assigned_courts' => [1],
        ]);

        $response = $this->put(route('tournament-days.update', $day->id), [
            'name' => $day->name,
            'date' => $day->date->format('Y-m-d'),
            'assigned_courts' => [2, 4],
        ]);

        $response->assertRedirect();

        $day->refresh();
        $subFolder->refresh();
        $unfiledTournament->refresh();
        $filedTournament->refresh();

        $this->assertSame([2, 4], $day->assigned_courts);
        $this->assertSame([], $subFolder->assigned_courts);
        $this->assertSame([2, 4], $unfiledTournament->assigned_courts);
        $this->assertSame([], $filedTournament->assigned_courts);
    }
}
