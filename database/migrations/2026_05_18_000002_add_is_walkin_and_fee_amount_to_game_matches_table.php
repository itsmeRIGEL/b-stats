<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->boolean('is_walkin')->default(false)->after('is_tallied');
            $table->decimal('fee_amount', 8, 2)->default(0.00)->after('is_walkin');
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn(['is_walkin', 'fee_amount']);
        });
    }
};
