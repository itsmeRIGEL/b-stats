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
            $table->text('assigned_courts')->nullable();
        });

        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->unsignedInteger('court_number')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_sub_folders', function (Blueprint $table) {
            $table->dropColumn('assigned_courts');
        });

        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropColumn('court_number');
        });
    }
};
