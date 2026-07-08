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
        Schema::table('players', function (Blueprint $table) {
            if (!Schema::hasColumn('players', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
        });

        Schema::table('game_matches', function (Blueprint $table) {
            if (!Schema::hasColumn('game_matches', 'submitted_by_user_id')) {
                $table->foreignId('submitted_by_user_id')->nullable()->after('venue_id')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('game_matches', 'submitted_by_role')) {
                $table->string('submitted_by_role')->nullable()->after('submitted_by_user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            if (Schema::hasColumn('game_matches', 'submitted_by_role')) {
                $table->dropColumn('submitted_by_role');
            }

            if (Schema::hasColumn('game_matches', 'submitted_by_user_id')) {
                $table->dropConstrainedForeignId('submitted_by_user_id');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });

        Schema::table('players', function (Blueprint $table) {
            if (Schema::hasColumn('players', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
