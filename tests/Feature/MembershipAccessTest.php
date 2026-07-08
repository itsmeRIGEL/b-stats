<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_membership_page()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $response = $this->get(route('memberships'));
        $response->assertStatus(200);
    }

    public function test_scheduler_can_access_membership_page()
    {
        $scheduler = User::factory()->create(['role' => 'scheduler']);
        $this->actingAs($scheduler);

        $response = $this->get(route('memberships'));
        $response->assertStatus(200);
    }

    public function test_scorer_cannot_access_membership_page()
    {
        $scorer = User::factory()->create(['role' => 'scorer']);
        $this->actingAs($scorer);

        $response = $this->get(route('memberships'));
        $response->assertRedirect(route('scoring'));
    }
}
