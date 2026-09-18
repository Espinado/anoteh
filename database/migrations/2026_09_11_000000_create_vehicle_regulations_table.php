<?php

use App\Enums\VehicleRegulationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_regulations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('regulation_type', 32);
            $table->decimal('due_odometer', 12, 1);
            $table->string('status', 16)->default(VehicleRegulationStatus::Planned->value)->index();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'status']);
        });

        if (Schema::hasColumn('vehicles', 'next_regulation')) {
            foreach (DB::table('vehicles')
                ->whereNotNull('next_regulation')
                ->whereNotNull('next_regulation_odometer')
                ->orderBy('id')
                ->get(['id', 'next_regulation', 'next_regulation_odometer']) as $vehicle) {
                DB::table('vehicle_regulations')->insert([
                    'vehicle_id' => $vehicle->id,
                    'regulation_type' => $vehicle->next_regulation,
                    'due_odometer' => $vehicle->next_regulation_odometer,
                    'status' => VehicleRegulationStatus::Planned->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_regulations');
    }
};
