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
        Schema::table('tournaments', function (Blueprint $table) {
            if (!Schema::hasColumn('tournaments', 'manager_user_id')) {
                $table->foreignId('manager_user_id')->nullable()->after('venue_id')->constrained('users')->nullOnDelete();
            }
        });

        Schema::create('tournament_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->default('mens');
            $table->date('preferred_date')->nullable();
            $table->string('preferred_start_time')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('tournament_id')->nullable()->constrained('tournaments')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_requests');

        Schema::table('tournaments', function (Blueprint $table) {
            if (Schema::hasColumn('tournaments', 'manager_user_id')) {
                $table->dropConstrainedForeignId('manager_user_id');
            }
        });
    }
};
