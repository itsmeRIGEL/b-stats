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
        Schema::table('date_availabilities', function (Blueprint $table) {
            $table->string('close_reason')->nullable()->after('is_closed');
        });

        Schema::table('day_availabilities', function (Blueprint $table) {
            $table->string('close_reason')->nullable()->after('is_closed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('date_availabilities', function (Blueprint $table) {
            $table->dropColumn('close_reason');
        });

        Schema::table('day_availabilities', function (Blueprint $table) {
            $table->dropColumn('close_reason');
        });
    }
};
