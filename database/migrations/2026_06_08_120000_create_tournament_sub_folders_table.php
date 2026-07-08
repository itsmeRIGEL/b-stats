<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_sub_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('tournament_day_id')
                ->constrained('tournament_days')
                ->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['tournament_day_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_sub_folders');
    }
};
