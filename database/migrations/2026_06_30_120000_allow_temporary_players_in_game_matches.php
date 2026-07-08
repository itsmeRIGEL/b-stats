<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            if (!Schema::hasColumn('game_matches', 'player_1_name')) {
                $table->string('player_1_name')->nullable()->after('player_1_id');
            }

            if (!Schema::hasColumn('game_matches', 'player_2_name')) {
                $table->string('player_2_name')->nullable()->after('player_2_id');
            }

            if (!Schema::hasColumn('game_matches', 'player_3_name')) {
                $table->string('player_3_name')->nullable()->after('player_3_id');
            }

            if (!Schema::hasColumn('game_matches', 'player_4_name')) {
                $table->string('player_4_name')->nullable()->after('player_4_id');
            }
        });

        Schema::table('game_matches', function (Blueprint $table) {
            $table->foreignId('player_1_id')->nullable()->change();
            $table->foreignId('player_2_id')->nullable()->change();
            $table->foreignId('player_3_id')->nullable()->change();
            $table->foreignId('player_4_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            if (Schema::hasColumn('game_matches', 'player_1_name')) {
                $table->dropColumn('player_1_name');
            }
            if (Schema::hasColumn('game_matches', 'player_2_name')) {
                $table->dropColumn('player_2_name');
            }
            if (Schema::hasColumn('game_matches', 'player_3_name')) {
                $table->dropColumn('player_3_name');
            }
            if (Schema::hasColumn('game_matches', 'player_4_name')) {
                $table->dropColumn('player_4_name');
            }
        });
    }
};
