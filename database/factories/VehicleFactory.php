<?php

namespace Database\Factories;

use App\Enums\FuelType;
use App\Enums\VehicleCategory;
use App\Enums\VehicleStatus;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicle> */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'registration_number' => strtoupper(fake()->bothify('??-####')),
            'vin' => strtoupper(fake()->unique()->bothify('?????????????????')),
            'make' => fake()->randomElement(['Volvo', 'Mercedes-Benz', 'Scania', 'Ford']),
            'model' => fake()->randomElement(['FH', 'Actros', 'R450', 'Transit']),
            'year' => fake()->numberBetween(2018, 2026),
            'status' => VehicleStatus::Active,
            'category' => VehicleCategory::Truck,
            'body_type' => fake()->randomElement(['tractor', 'box', 'curtain_sider']),
            'fuel_type' => FuelType::Diesel,
            'inspection_until' => fake()->optional()->dateTimeBetween('now', '+2 years'),
            'octa_until' => fake()->optional()->dateTimeBetween('now', '+1 year'),
            'commissioned_on' => fake()->dateTimeBetween('-5 years', 'now'),
            'current_odometer' => fake()->randomFloat(1, 20000, 500000),
        ];
    }
}
