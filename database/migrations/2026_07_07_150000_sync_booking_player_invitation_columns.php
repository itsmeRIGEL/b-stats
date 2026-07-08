<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_player', function (Blueprint $table) {
            if (!Schema::hasColumn('booking_player', 'status')) {
                $table->string('status')->default('accepted')->after('player_id');
            }

            if (!Schema::hasColumn('booking_player', 'invited_by_user_id')) {
                $table->foreignId('invited_by_user_id')
                    ->nullable()
                    ->after(Schema::hasColumn('booking_player', 'status') ? 'status' : 'player_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('booking_player', 'responded_at')) {
                $afterColumn = Schema::hasColumn('booking_player', 'invited_by_user_id') ? 'invited_by_user_id' : (Schema::hasColumn('booking_player', 'status') ? 'status' : 'player_id');
                $table->timestamp('responded_at')->nullable()->after($afterColumn);
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_player', function (Blueprint $table) {
            if (Schema::hasColumn('booking_player', 'responded_at')) {
                $table->dropColumn('responded_at');
            }

            if (Schema::hasColumn('booking_player', 'invited_by_user_id')) {
                $table->dropConstrainedForeignId('invited_by_user_id');
            }

            if (Schema::hasColumn('booking_player', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
