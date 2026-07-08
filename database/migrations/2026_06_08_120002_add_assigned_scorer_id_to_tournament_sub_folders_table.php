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
            $table->foreignId('assigned_scorer_id')->nullable()->after('order')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_sub_folders', function (Blueprint $table) {
            $table->dropForeign(['assigned_scorer_id']);
            $table->dropColumn('assigned_scorer_id');
        });
    }
};
