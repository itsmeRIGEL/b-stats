<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_scorer_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('court_number');
            $table->foreignId('scorer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('assignment_date');
            $table->timestamps();

            $table->unique(['court_number', 'assignment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_scorer_assignments');
    }
};
