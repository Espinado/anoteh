<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE vehicle_regulations MODIFY regulation_type VARCHAR(120) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vehicle_regulations MODIFY regulation_type VARCHAR(32) NOT NULL');
    }
};
