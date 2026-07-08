<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'facebook_url')) {
                $table->string('facebook_url')->nullable()->after('avatar');
            }

            if (!Schema::hasColumn('users', 'instagram_url')) {
                $table->string('instagram_url')->nullable()->after('facebook_url');
            }

            if (!Schema::hasColumn('users', 'website_url')) {
                $table->string('website_url')->nullable()->after('instagram_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'website_url')) {
                $table->dropColumn('website_url');
            }

            if (Schema::hasColumn('users', 'instagram_url')) {
                $table->dropColumn('instagram_url');
            }

            if (Schema::hasColumn('users', 'facebook_url')) {
                $table->dropColumn('facebook_url');
            }
        });
    }
};
