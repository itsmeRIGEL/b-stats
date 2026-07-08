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
        Schema::table('booking_player', function (Blueprint $table) {
            $table->string('status')->default('accepted')->after('player_id');
            $table->foreignId('invited_by_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable()->after('invited_by_user_id');
            $table->unique(['booking_id', 'player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_player', function (Blueprint $table) {
            $table->dropUnique(['booking_id', 'player_id']);
            $table->dropConstrainedForeignId('invited_by_user_id');
            $table->dropColumn(['status', 'responded_at']);
        });
    }
};
