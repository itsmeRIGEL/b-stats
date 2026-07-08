<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchedulerPaymentReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_can_save_payment_reference_per_venue(): void
    {
        Storage::fake('public');

        $scheduler = User::factory()->create([
            'role' => 'scheduler',
            'email_verified_at' => now(),
        ]);

        $venue = Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Toolbar Court',
            'opening_time' => '08:00',
            'closing_time' => '22:00',
            'default_hourly_rate' => 180,
            'member_booking_fee' => 180,
            'non_member_booking_fee' => 200,
            'court_count' => 3,
            'is_active' => true,
        ]);

        $response = $this->actingAs($scheduler)->post(route('pickleball-settings.update'), [
            'payment_account_name' => 'Toolbar Court - GCash',
            'payment_qr_photo' => UploadedFile::fake()->image('payment-qr.png'),
        ]);

        $response->assertRedirect();

        $venue->refresh();

        $this->assertSame('Toolbar Court - GCash', $venue->payment_account_name);
        $this->assertNotNull($venue->payment_qr_photo);
        $this->assertStringContainsString('venue-payment-qrs/', $venue->payment_qr_photo);
    }
}
