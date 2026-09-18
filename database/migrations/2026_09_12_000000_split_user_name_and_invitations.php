<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 80)->nullable()->after('id');
            $table->string('last_name', 80)->nullable()->after('first_name');
        });

        DB::table('users')->orderBy('id')->lazyById()->each(function (object $user): void {
            $parts = preg_split('/\s+/', trim((string) $user->name), 2) ?: ['', ''];

            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $parts[0] !== '' ? $parts[0] : 'User',
                'last_name' => $parts[1] ?? '',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        DB::table('users')->orderBy('id')->lazyById()->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'name' => trim(((string) $user->first_name).' '.((string) $user->last_name)),
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
