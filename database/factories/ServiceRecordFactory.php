<?php

namespace Database\Factories;

use App\Enums\ServiceStatus;
use App\Enums\ServiceType;
use App\Models\ServiceRecord;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceRecord> */
class ServiceRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'created_by' => User::factory(),
            'status' => ServiceStatus::Draft,
            'service_type' => fake()->randomElement(ServiceType::cases()),
            'provider_name' => fake()->company(),
            'planned_at' => now('UTC')->addDays(3),
            'odometer' => fake()->numberBetween(10000, 300000),
            'currency' => 'EUR',
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
        ];
    }
}
