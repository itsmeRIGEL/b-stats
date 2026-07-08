<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('tagline')->nullable()->after('address');
            $table->text('description')->nullable()->after('tagline');
            $table->string('logo_path')->nullable()->after('description');
            $table->string('cover_photo_path')->nullable()->after('logo_path');
            $table->json('gallery_paths')->nullable()->after('cover_photo_path');
            $table->string('contact_email')->nullable()->after('gallery_paths');
            $table->string('contact_phone')->nullable()->after('contact_email');
            $table->string('facebook_url')->nullable()->after('contact_phone');
            $table->json('amenities')->nullable()->after('facebook_url');
            $table->unsignedInteger('covered_court_count')->nullable()->after('amenities');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn([
                'tagline',
                'description',
                'logo_path',
                'cover_photo_path',
                'gallery_paths',
                'contact_email',
                'contact_phone',
                'facebook_url',
                'amenities',
                'covered_court_count',
            ]);
        });
    }
};
