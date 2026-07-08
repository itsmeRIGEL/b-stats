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
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('start_time')->default('08:00');
            $table->integer('match_duration')->default(25);
            $table->integer('rest_time')->default(5);
            $table->boolean('enable_break')->default(false);
            $table->string('break_start')->nullable();
            $table->string('break_end')->nullable();
        });

        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->string('scheduled_time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'match_duration', 'rest_time', 'enable_break', 'break_start', 'break_end']);
        });

        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropColumn('scheduled_time');
        });
    }
};
