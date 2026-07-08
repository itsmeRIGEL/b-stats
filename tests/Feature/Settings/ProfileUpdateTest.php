<?php

namespace Tests\Feature\Settings;

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makePlayer(User $user, array $overrides = []): Player
    {
        return Player::create(array_merge([
            'user_id' => $user->id,
            'name' => $user->name,
            'full_name' => $user->name,
            'phone' => '09170000000',
            'birthday' => '1996-01-01',
            'address' => 'Cagayan de Oro',
            'is_member' => true,
            'total_matches' => 12,
            'wins' => 8,
            'losses' => 4,
            'venue_id' => null,
        ], $overrides));
    }

    public function test_profile_page_shows_profile_stats_and_player_profile()
    {
        $user = User::factory()->create([
            'role' => 'player',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'suffix' => 'Jr.',
            'username' => 'juandelacruz',
        ]);

        $player = $this->makePlayer($user);

        $response = $this
            ->actingAs($user)
            ->get('/settings/profile');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/Profile')
            ->where('playerProfile.id', $player->id)
            ->where('playerProfile.phone', '09170000000')
            ->where('playerProfile.birthday', '1996-01-01')
            ->where('profileStats.wins', 8)
            ->where('profileStats.losses', 4)
            ->where('profileStats.total_matches', 12)
        );
    }

    public function test_profile_information_can_be_updated_and_player_record_is_synced()
    {
        $user = User::factory()->create([
            'role' => 'player',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'username' => 'juandelacruz',
        ]);

        $this->makePlayer($user);

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'first_name' => 'Cruzalex',
                'middle_name' => 'Rigel',
                'last_name' => 'Branzuela',
                'suffix' => 'III',
                'username' => 'thestarrigel',
                'email' => 'rigel@example.com',
                'facebook_url' => 'https://facebook.com/cruzalex',
                'instagram_url' => 'https://instagram.com/cruzalex',
                'website_url' => 'https://cruzalex.example.com',
                'phone' => '09171112222',
                'birthday' => '1998-02-14',
                'address' => 'Sayre Highway, Cagayan de Oro',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $user->refresh();

        $this->assertSame('Cruzalex Rigel Branzuela III', $user->name);
        $this->assertSame('thestarrigel', $user->username);
        $this->assertSame('rigel@example.com', $user->email);
        $this->assertNull($user->email_verified_at);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Cruzalex Rigel Branzuela III',
            'username' => 'thestarrigel',
            'email' => 'rigel@example.com',
            'facebook_url' => 'https://facebook.com/cruzalex',
            'instagram_url' => 'https://instagram.com/cruzalex',
            'website_url' => 'https://cruzalex.example.com',
        ]);

        $this->assertDatabaseHas('players', [
            'user_id' => $user->id,
            'name' => 'Cruzalex Rigel Branzuela III',
            'full_name' => 'Cruzalex Rigel Branzuela III',
            'phone' => '09171112222',
            'birthday' => '1998-02-14',
            'address' => 'Sayre Highway, Cagayan de Oro',
        ]);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create([
            'role' => 'player',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'username' => 'juandelacruz',
        ]);

        $this->makePlayer($user);

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'first_name' => 'Juan',
                'middle_name' => null,
                'last_name' => 'Dela Cruz',
                'suffix' => null,
                'username' => 'juandelacruz',
                'email' => $user->email,
                'phone' => '09170000001',
                'birthday' => '1997-05-10',
                'address' => 'Old address',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/settings/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/profile')
            ->delete('/settings/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->fresh());
    }
}
