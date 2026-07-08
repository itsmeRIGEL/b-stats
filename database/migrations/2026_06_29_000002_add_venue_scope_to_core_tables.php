<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('scheduler_id')->nullable()->after('role')->constrained('users')->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->after('scheduler_id')->constrained('venues')->nullOnDelete();
        });

        $venueId = DB::table('venues')->insertGetId([
            'scheduler_id' => null,
            'name' => 'Legacy Venue',
            'address' => null,
            'court_count' => (int) (DB::table('system_settings')->where('key', 'court_count')->value('value') ?? 1),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $coreTables = [
            'bookings',
            'players',
            'game_matches',
            'tournaments',
            'tournament_days',
            'tournament_sub_folders',
            'tournament_matches',
            'court_scorer_assignments',
            'day_availabilities',
            'date_availabilities',
        ];

        foreach ($coreTables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('venue_id')->nullable()->after('id')->constrained('venues')->nullOnDelete();
            });
            DB::table($table)->update(['venue_id' => $venueId]);
        }

        DB::table('users')
            ->whereIn('role', ['scheduler', 'scorer', 'scheduler_scorer'])
            ->update(['venue_id' => $venueId]);
    }

    public function down(): void
    {
        $coreTables = [
            'date_availabilities',
            'day_availabilities',
            'court_scorer_assignments',
            'tournament_matches',
            'tournament_sub_folders',
            'tournament_days',
            'tournaments',
            'game_matches',
            'players',
            'bookings',
        ];

        foreach ($coreTables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('venue_id');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venue_id');
            $table->dropConstrainedForeignId('scheduler_id');
        });
    }
};
