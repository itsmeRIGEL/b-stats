<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedBigInteger('tournament_day_id')->nullable()->after('category');

            $table->foreign('tournament_day_id')
                ->references('id')
                ->on('tournament_days')
                ->nullOnDelete();

            $table->index('tournament_day_id');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['tournament_day_id']);
            $table->dropIndex(['tournament_day_id']);
            $table->dropColumn('tournament_day_id');
        });
    }
};
