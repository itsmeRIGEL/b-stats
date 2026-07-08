<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('app_name')->nullable()->after('is_active');
            $table->string('opening_time', 5)->nullable()->after('app_name');
            $table->string('closing_time', 5)->nullable()->after('opening_time');
            $table->decimal('default_hourly_rate', 10, 2)->nullable()->after('closing_time');
            $table->decimal('member_booking_fee', 10, 2)->nullable()->after('default_hourly_rate');
            $table->decimal('non_member_booking_fee', 10, 2)->nullable()->after('member_booking_fee');
            $table->decimal('membership_monthly_fee', 10, 2)->nullable()->after('non_member_booking_fee');
            $table->decimal('membership_yearly_fee', 10, 2)->nullable()->after('membership_monthly_fee');
            $table->decimal('walkin_member_fee', 10, 2)->nullable()->after('membership_yearly_fee');
            $table->decimal('walkin_non_member_fee', 10, 2)->nullable()->after('walkin_member_fee');
            $table->decimal('walkin_ball_surcharge', 10, 2)->nullable()->after('walkin_non_member_fee');
            $table->unsignedInteger('booking_expiration_grace_minutes')->nullable()->after('walkin_ball_surcharge');
            $table->boolean('allow_past_edits')->default(false)->after('booking_expiration_grace_minutes');
            $table->unsignedInteger('refund_full_hours')->nullable()->after('allow_past_edits');
            $table->unsignedInteger('refund_full_mins')->nullable()->after('refund_full_hours');
            $table->unsignedTinyInteger('refund_full_pct')->nullable()->after('refund_full_mins');
            $table->unsignedInteger('refund_partial_hours')->nullable()->after('refund_full_pct');
            $table->unsignedInteger('refund_partial_mins')->nullable()->after('refund_partial_hours');
            $table->unsignedTinyInteger('refund_partial_pct')->nullable()->after('refund_partial_mins');
            $table->unsignedTinyInteger('refund_no_pct')->nullable()->after('refund_partial_pct');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn([
                'app_name',
                'opening_time',
                'closing_time',
                'default_hourly_rate',
                'member_booking_fee',
                'non_member_booking_fee',
                'membership_monthly_fee',
                'membership_yearly_fee',
                'walkin_member_fee',
                'walkin_non_member_fee',
                'walkin_ball_surcharge',
                'booking_expiration_grace_minutes',
                'allow_past_edits',
                'refund_full_hours',
                'refund_full_mins',
                'refund_full_pct',
                'refund_partial_hours',
                'refund_partial_mins',
                'refund_partial_pct',
                'refund_no_pct',
            ]);
        });
    }
};
