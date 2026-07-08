<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_booking_automatically_marks_as_paid()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $booking = Booking::create([
            'booking_date' => '2026-06-05',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'cost_per_hour' => 200,
            'total_cost' => 200,
            'lead_name' => 'John Doe',
            'lead_address' => 'Test Address',
            'player_count' => 4,
            'court_number' => 1,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $response = $this->post(route('bookings.approve', $booking));

        $response->assertRedirect();
        
        $booking->refresh();
        $this->assertEquals('approved', $booking->status);
        $this->assertEquals('paid', $booking->payment_status);
        $this->assertEquals($user->id, $booking->approved_by);
    }
}
