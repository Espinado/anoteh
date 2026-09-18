<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->string('next_regulation', 32)->nullable()->after('octa_until');
            $table->decimal('next_regulation_odometer', 12, 1)->nullable()->after('next_regulation');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropColumn(['next_regulation', 'next_regulation_odometer']);
        });
    }
};
