<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->integer('round');
            $table->integer('match_order');
            $table->string('bracket')->default('winners'); // winners, losers (for double elim)
            $table->foreignId('team1_id')->nullable()->constrained('tournament_players')->onDelete('set null');
            $table->foreignId('team2_id')->nullable()->constrained('tournament_players')->onDelete('set null');
            $table->integer('team1_score')->nullable();
            $table->integer('team2_score')->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('tournament_players')->onDelete('set null');
            $table->foreignId('next_match_id')->nullable()->constrained('tournament_matches')->onDelete('set null');
            $table->string('next_match_slot')->nullable(); // team1 or team2
            $table->foreignId('loser_next_match_id')->nullable()->constrained('tournament_matches')->onDelete('set null');
            $table->string('loser_next_match_slot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_matches');
    }
};
