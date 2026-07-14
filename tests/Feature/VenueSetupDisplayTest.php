<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueSetupDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_can_save_extended_venue_display_details(): void
    {
        $scheduler = User::factory()->create([
            'role' => 'scheduler',
            'email_verified_at' => now(),
        ]);

        Venue::create([
            'scheduler_id' => $scheduler->id,
            'name' => 'Existing Venue',
            'opening_time' => '06:00',
            'closing_time' => '23:00',
            'default_hourly_rate' => 250,
            'member_booking_fee' => 200,
            'non_member_booking_fee' => 250,
            'membership_monthly_fee' => 20,
            'membership_yearly_fee' => 60,
            'walkin_member_fee' => 15,
            'walkin_non_member_fee' => 20,
            'walkin_ball_surcharge' => 5,
            'booking_expiration_grace_minutes' => 10,
            'allow_past_edits' => true,
            'refund_full_hours' => 48,
            'refund_full_mins' => 0,
            'refund_full_pct' => 100,
            'refund_partial_hours' => 24,
            'refund_partial_mins' => 0,
            'refund_partial_pct' => 50,
            'refund_no_pct' => 0,
            'court_count' => 2,
            'is_active' => true,
        ]);

        \Illuminate\Support\Facades\Storage::fake('public');

        $this->actingAs($scheduler)
            ->post(route('venue-setup.store'), [
                'name' => 'Puerto Pickle Bay',
                'address' => 'Sayre Highway, Cagayan de Oro',
                'tagline' => 'Built for the community.',
                'description' => 'A welcoming venue with covered courts and player amenities.',
                'contact_email' => 'venue@example.com',
                'contact_phone' => '+639001112222',
                'facebook_url' => 'https://facebook.com/puertopicklebay',
                'amenities' => 'Parking, Restrooms, Waiting Area',
                'covered_court_count' => 2,
                'logo_photo' => \Illuminate\Http\UploadedFile::fake()->image('logo.png'),
                'cover_photo' => \Illuminate\Http\UploadedFile::fake()->image('cover.png'),
                'gallery_photos' => [
                    \Illuminate\Http\UploadedFile::fake()->image('gallery1.png'),
                    \Illuminate\Http\UploadedFile::fake()->image('gallery2.png'),
                ],
                'existing_gallery_paths' => json_encode(['/storage/venue-gallery/existing1.png']),
            ])
            ->assertRedirect(route('venue-setup'));

        $venue = Venue::where('scheduler_id', $scheduler->id)->firstOrFail();

        $this->assertSame($scheduler->id, $venue->scheduler_id);
        $this->assertSame('Puerto Pickle Bay', $venue->name);
        $this->assertSame('Built for the community.', $venue->tagline);
        $this->assertSame('venue@example.com', $venue->contact_email);
        $this->assertSame(['Parking', 'Restrooms', 'Waiting Area'], $venue->amenities);
        $this->assertSame(2, $venue->covered_court_count);
        $this->assertSame(2, $venue->court_count);
        $this->assertSame('06:00', $venue->opening_time);
        $this->assertSame('23:00', $venue->closing_time);
        $this->assertSame('250.00', number_format((float) $venue->default_hourly_rate, 2, '.', ''));
        
        $this->assertNotNull($venue->logo_path);
        $this->assertNotNull($venue->cover_photo_path);
        $this->assertCount(3, $venue->gallery_paths);
        $this->assertContains('venue-gallery/existing1.png', $venue->gallery_paths);
    }
}
