<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->foreignId('tournament_sub_folder_id')
                ->nullable()
                ->after('tournament_day_id')
                ->constrained('tournament_sub_folders')
                ->nullOnDelete();

            $table->index('tournament_sub_folder_id');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['tournament_sub_folder_id']);
            $table->dropIndex(['tournament_sub_folder_id']);
            $table->dropColumn('tournament_sub_folder_id');
        });
    }
};
