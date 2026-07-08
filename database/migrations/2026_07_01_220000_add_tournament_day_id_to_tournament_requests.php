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
            if (!Schema::hasColumn('tournament_requests', 'tournament_day_id')) {
                $table->foreignId('tournament_day_id')->nullable()->after('tournament_id')->constrained('tournament_days')->nullOnDelete();
            }
        });

        if (!Schema::hasTable('tournaments') || !Schema::hasColumn('tournaments', 'tournament_day_id')) {
            return;
        }

        $requestsToBackfill = DB::table('tournament_requests')
            ->join('tournaments', 'tournament_requests.tournament_id', '=', 'tournaments.id')
            ->whereNull('tournament_requests.tournament_day_id')
            ->whereNotNull('tournaments.tournament_day_id')
            ->select([
                'tournament_requests.id as request_id',
                'tournaments.tournament_day_id as tournament_day_id',
            ])
            ->get();

        foreach ($requestsToBackfill as $requestToBackfill) {
            DB::table('tournament_requests')
                ->where('id', $requestToBackfill->request_id)
                ->update([
                    'tournament_day_id' => $requestToBackfill->tournament_day_id,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('tournament_requests', function (Blueprint $table) {
            if (Schema::hasColumn('tournament_requests', 'tournament_day_id')) {
                $table->dropConstrainedForeignId('tournament_day_id');
            }
        });
    }
};
