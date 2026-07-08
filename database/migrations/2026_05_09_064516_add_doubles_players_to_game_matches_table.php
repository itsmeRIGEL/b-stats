<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->foreignId('player_3_id')->nullable()->after('player_2_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('player_4_id')->nullable()->after('player_3_id')->constrained('players')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('player_3_id');
            $table->dropConstrainedForeignId('player_4_id');
        });
    }
};
