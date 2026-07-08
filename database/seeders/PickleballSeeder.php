<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Player;
use App\Models\Booking;
use App\Models\GameMatch;

class PickleballSeeder extends Seeder
{
    public function run(): void
    {
        $players = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'is_member' => true, 'membership_expires_at' => now()->addMonth()],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'is_member' => true, 'membership_expires_at' => now()->addMonth()],
            ['name' => 'Mike Johnson', 'email' => 'mike@example.com', 'is_member' => false, 'membership_expires_at' => null],
            ['name' => 'Sarah Williams', 'email' => 'sarah@example.com', 'is_member' => true, 'membership_expires_at' => now()->addMonth()],
            ['name' => 'David Brown', 'email' => 'david@example.com', 'is_member' => false, 'membership_expires_at' => null],
        ];

        foreach ($players as $playerData) {
            Player::create($playerData);
        }

        $allPlayers = Player::all();

        // Create some bookings
        for ($i = 0; $i < 5; $i++) {
            $booking = Booking::create([
                'booking_date' => now()->addDays($i)->toDateString(),
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'cost_per_hour' => 25.00,
                'total_cost' => 50.00,
                'lead_name' => 'Lead Player ' . ($i + 1),
                'lead_address' => 'Sample Address ' . ($i + 1),
                'player_count' => rand(2, 4),
            ]);

            $booking->players()->attach($allPlayers->random(rand(2, 4))->pluck('id'));
        }

        // Create some matches
        for ($i = 0; $i < 10; $i++) {
            $p1 = $allPlayers->random();
            $p2 = $allPlayers->where('id', '!=', $p1->id)->random();
            
            $score1 = rand(5, 21);
            $score2 = rand(5, 21);
            while($score1 == $score2) $score2 = rand(5, 21);

            GameMatch::create([
                'player_1_id' => $p1->id,
                'player_2_id' => $p2->id,
                'player_1_score' => $score1,
                'player_2_score' => $score2,
                'match_date' => now()->subDays(rand(1, 10))->toDateString(),
            ]);

            $p1->increment('total_matches');
            $p2->increment('total_matches');

            if ($score1 > $score2) {
                $p1->increment('wins');
                $p2->increment('losses');
            } else {
                $p2->increment('wins');
                $p1->increment('losses');
            }
        }
    }
}
