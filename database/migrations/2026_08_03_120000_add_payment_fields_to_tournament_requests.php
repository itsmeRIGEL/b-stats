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
        Schema::table('tournament_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('tournament_requests', 'receipt_photo')) {
                $table->string('receipt_photo')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('tournament_requests', 'total_cost')) {
                $table->decimal('total_cost', 10, 2)->nullable()->after('receipt_photo');
            }
            if (!Schema::hasColumn('tournament_requests', 'payment_status')) {
                $table->string('payment_status')->default('unpaid')->after('total_cost');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_requests', function (Blueprint $table) {
            $table->dropColumn(['receipt_photo', 'total_cost', 'payment_status']);
        });
    }
};
