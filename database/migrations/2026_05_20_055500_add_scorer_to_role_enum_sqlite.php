<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite requires recreating the table to modify enums
        // Get all existing users
        $users = DB::table('users')->get();

        // Drop the role column and recreate as text (SQLite doesn't enforce enum constraints anyway)
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('admin')->after('email');
        });

        // Restore user roles
        foreach ($users as $user) {
            DB::table('users')->where('id', $user->id)->update(['role' => $user->role ?? 'admin']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $users = DB::table('users')->get();

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'scheduler'])->default('admin')->after('email');
        });

        foreach ($users as $user) {
            $role = in_array($user->role, ['admin', 'scheduler']) ? $user->role : 'admin';
            DB::table('users')->where('id', $user->id)->update(['role' => $role]);
        }
    }
};
