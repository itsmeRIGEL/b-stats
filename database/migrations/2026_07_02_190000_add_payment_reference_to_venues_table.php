<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            if (!Schema::hasColumn('venues', 'payment_account_name')) {
                $table->string('payment_account_name')->nullable()->after('refund_no_pct');
            }

            if (!Schema::hasColumn('venues', 'payment_qr_photo')) {
                $table->string('payment_qr_photo')->nullable()->after('payment_account_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            if (Schema::hasColumn('venues', 'payment_qr_photo')) {
                $table->dropColumn('payment_qr_photo');
            }

            if (Schema::hasColumn('venues', 'payment_account_name')) {
                $table->dropColumn('payment_account_name');
            }
        });
    }
};
