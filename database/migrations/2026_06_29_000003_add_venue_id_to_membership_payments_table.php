<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->after('player_id')->constrained('venues')->nullOnDelete();
        });

        $legacyVenueId = DB::table('venues')->where('name', 'Legacy Venue')->value('id');
        if ($legacyVenueId) {
            DB::table('membership_payments')->update(['venue_id' => $legacyVenueId]);
        }
    }

    public function down(): void
    {
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venue_id');
        });
    }
};
