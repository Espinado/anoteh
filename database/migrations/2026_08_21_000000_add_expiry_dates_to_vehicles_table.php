<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->date('inspection_until')->nullable()->after('fuel_type')->index();
            $table->date('octa_until')->nullable()->after('inspection_until')->index();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropIndex(['inspection_until']);
            $table->dropIndex(['octa_until']);
            $table->dropColumn(['inspection_until', 'octa_until']);
        });
    }
};
