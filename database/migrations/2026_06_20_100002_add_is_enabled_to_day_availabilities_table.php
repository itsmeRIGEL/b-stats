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
        Schema::table('day_availabilities', function (Blueprint $table) {
            $table->boolean('is_enabled')->default(false)->after('day_of_week');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('day_availabilities', function (Blueprint $table) {
            $table->dropColumn('is_enabled');
        });
    }
};
