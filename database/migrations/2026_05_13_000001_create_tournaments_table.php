<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['single_elimination', 'double_elimination', 'round_robin']);
            $table->enum('status', ['setup', 'in_progress', 'completed'])->default('setup');
            $table->integer('min_players')->default(4);
            $table->integer('max_players')->default(16);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
