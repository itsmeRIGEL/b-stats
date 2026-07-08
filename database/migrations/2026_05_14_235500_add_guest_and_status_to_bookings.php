<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('court_number');
            $table->string('guest_email')->nullable()->after('lead_address');
            $table->string('guest_phone')->nullable()->after('guest_email');
            $table->string('receipt_photo')->nullable()->after('guest_phone');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['status', 'guest_email', 'guest_phone', 'receipt_photo']);
        });
    }
};
