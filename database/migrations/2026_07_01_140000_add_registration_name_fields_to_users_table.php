<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('first_name')->nullable()->after('username');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
        });

        DB::table('users')
            ->orderBy('id')
            ->get(['id', 'name', 'email'])
            ->each(function ($user) {
                $nameParts = preg_split('/\s+/', trim((string) $user->name)) ?: [];
                $firstName = $nameParts[0] ?? null;
                $lastName = count($nameParts) > 1 ? $nameParts[count($nameParts) - 1] : null;
                $middleName = count($nameParts) > 2
                    ? implode(' ', array_slice($nameParts, 1, -1))
                    : null;

                $baseUsername = Str::slug(Str::before((string) $user->email, '@'), '_');
                if ($baseUsername === '') {
                    $baseUsername = 'user_' . $user->id;
                }

                $username = $baseUsername;
                $suffix = 1;
                while (
                    DB::table('users')
                        ->where('username', $username)
                        ->where('id', '!=', $user->id)
                        ->exists()
                ) {
                    $username = $baseUsername . '_' . $suffix;
                    $suffix++;
                }

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'username' => $username,
                        'first_name' => $firstName,
                        'middle_name' => $middleName,
                        'last_name' => $lastName,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'first_name', 'middle_name', 'last_name']);
        });
    }
};
