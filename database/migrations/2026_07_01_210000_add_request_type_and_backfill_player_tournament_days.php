<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('tournament_requests', 'request_type')) {
                $table->string('request_type')->default('new_tournament')->after('notes');
            }
        });

        if (!Schema::hasTable('tournament_days')) {
            return;
        }

        if (!Schema::hasTable('tournaments') || !Schema::hasColumn('tournaments', 'tournament_day_id')) {
            return;
        }

        $tournaments = DB::table('tournaments')
            ->whereNotNull('manager_user_id')
            ->whereNull('tournament_day_id')
            ->get();

        foreach ($tournaments as $tournament) {
            $request = DB::table('tournament_requests')
                ->where('tournament_id', $tournament->id)
                ->orderByDesc('id')
                ->first();

            $dayDate = $request?->preferred_date
                ?: ($tournament->created_at ? \Illuminate\Support\Carbon::parse($tournament->created_at)->toDateString() : now()->toDateString());

            $dayId = DB::table('tournament_days')->insertGetId([
                'name' => $request?->name ?: $tournament->name,
                'date' => $dayDate,
                'status' => 'active',
                'venue_id' => $tournament->venue_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('tournaments')
                ->where('id', $tournament->id)
                ->update([
                    'tournament_day_id' => $dayId,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('tournament_requests', function (Blueprint $table) {
            if (Schema::hasColumn('tournament_requests', 'request_type')) {
                $table->dropColumn('request_type');
            }
        });
    }
};
