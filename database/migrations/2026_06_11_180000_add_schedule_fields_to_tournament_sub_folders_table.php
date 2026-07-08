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
        Schema::table('tournament_sub_folders', function (Blueprint $table) {
            $table->string('start_time')->default('08:00');
            $table->integer('match_duration')->default(25);
            $table->integer('rest_time')->default(5);
            $table->boolean('enable_break')->default(false);
            $table->string('break_start')->nullable();
            $table->string('break_end')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_sub_folders', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'match_duration', 'rest_time', 'enable_break', 'break_start', 'break_end']);
        });
    }
};
